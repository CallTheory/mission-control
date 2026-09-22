<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\MoveSuccessfulFaxFiles;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The core regression for multi-source faxing: two Intelligent Series servers issue the
 * same short `.fs` names (IS20.fs), so anything keyed on the filename alone collides.
 */
class MultiSourceSpoolTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        // A temp root, so this never touches the tracked storage/app spool directories.
        $this->root = storage_path('framework/testing/fax-'.uniqid());

        FaxSpoolSource::create([
            'key' => 'is2',
            'name' => 'IS 2',
            'driver' => FaxSpoolSource::DRIVER_LOCAL,
            'root_path' => $this->root,
        ]);

        foreach (FaxSpool::allFolders() as $folder) {
            File::ensureDirectoryExists("{$this->root}/{$folder}");
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_a_second_source_reads_its_own_directory(): void
    {
        File::put("{$this->root}/tosend/IS20.fs", '$var_def DATA5 "4242"');

        $spool = new FaxSpool;

        $this->assertSame(
            ['IS20.fs'],
            array_column($spool->files('mfax', 'tosend', 'is2'), 'name')
        );

        // The legacy source is a different directory entirely and must not see it.
        $this->assertSame([], array_column($spool->files('mfax', 'tosend', 'mfax'), 'name'));
    }

    public function test_deleting_one_sources_fs_leaves_the_others_pending_fax_alone(): void
    {
        $shared = [
            'api_fax_id' => 'x',
            'fax_provider' => 'mfax',
            'job_id' => 1,
            'fs_file_name' => 'IS20.fs',
            'cap_file' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'original_status' => '2',
            'delivery_status' => 'pending',
        ];

        $legacy = PendingFax::create($shared + ['spool_source_key' => 'mfax']);
        $other = PendingFax::create(['api_fax_id' => 'y'] + $shared + ['spool_source_key' => 'is2']);

        File::put("{$this->root}/tosend/IS20.fs", 'x');

        (new FaxSpool)->delete('mfax', 'tosend', 'IS20.fs', 'tester', 'is2');

        // Only the source whose file was actually deleted stops being chased.
        $this->assertSame('failed', $other->fresh()->delivery_status);
        $this->assertSame('pending', $legacy->fresh()->delivery_status);
    }

    public function test_move_jobs_for_the_same_fs_name_do_not_share_a_unique_lock(): void
    {
        $details = [
            'jobID' => 1,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
        ];

        // Sharing a key means the second job is silently dropped by
        // PendingDispatch::__destruct and that server's .fs rots in tosend/.
        $this->assertNotSame(
            (new MoveSuccessfulFaxFiles($details, 'mfax'))->uniqueId(),
            (new MoveSuccessfulFaxFiles($details, 'mfax', 'is2'))->uniqueId(),
        );

        // ...while the legacy source keeps the exact key it locked on before sources
        // existed, so an in-flight job released after the deploy still matches.
        $this->assertSame('IS20.fs', (new MoveSuccessfulFaxFiles($details, 'mfax'))->uniqueId());
    }

    public function test_a_move_job_writes_into_its_own_sources_folders(): void
    {
        File::put("{$this->root}/tosend/IS20.fs", "\$fax_status1 2\ntosend\n");
        File::put("{$this->root}/tosend/IS20.cap", 'payload');

        (new MoveSuccessfulFaxFiles([
            'jobID' => 1,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
        ], 'mfax', 'is2'))->handle();

        $this->assertFileExists("{$this->root}/sent/IS20.fs");
        $this->assertFileExists("{$this->root}/sent/IS20.cap");
        $this->assertFileDoesNotExist("{$this->root}/tosend/IS20.fs");

        // The status IS reads back, rewritten in place.
        $this->assertStringContainsString('$fax_status2 0', File::get("{$this->root}/sent/IS20.fs"));
    }
}
