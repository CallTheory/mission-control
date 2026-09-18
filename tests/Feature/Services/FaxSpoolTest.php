<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Jobs\SendFaxRingCentral;
use App\Models\PendingFax;
use App\Services\Faxing\FaxSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class FaxSpoolTest extends TestCase
{
    use RefreshDatabase;

    private array $dirs = ['tosend', 'sent', 'fail', 'preproc'];

    /**
     * The spool directories ship with a tracked .gitignore. These tests assert that it is
     * left alone by listings and folder clears, which means touching it — so its contents
     * are captured here and put back afterwards rather than left rewritten in the repo.
     *
     * @var array<string, string|null>
     */
    private array $gitignores = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        foreach ($this->dirs as $dir) {
            $path = storage_path("app/ringcentral/{$dir}/");
            $this->emptyDir($path);

            $this->gitignores[$path] = is_file($path.'.gitignore')
                ? file_get_contents($path.'.gitignore')
                : null;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            $this->emptyDir(storage_path("app/ringcentral/{$dir}/"));
        }

        foreach ($this->gitignores as $path => $contents) {
            if ($contents === null) {
                @unlink($path.'.gitignore');
            } else {
                file_put_contents($path.'.gitignore', $contents);
            }
        }

        parent::tearDown();
    }

    public function test_it_describes_files_in_a_folder(): void
    {
        $this->writeFs('IS20.fs', 4242, 'IS20.cap');
        file_put_contents(storage_path('app/ringcentral/tosend/IS20.cap'), 'payload');
        file_put_contents(storage_path('app/ringcentral/tosend/stray.tmp'), 'x');

        $files = array_column((new FaxSpool)->files('ringcentral', 'tosend'), null, 'name');

        $this->assertSame('fs', $files['IS20.fs']['type']);
        $this->assertSame(4242, $files['IS20.fs']['job_id']);
        $this->assertSame('cap', $files['IS20.cap']['type']);
        $this->assertSame(7, $files['IS20.cap']['size']);
        // Anything that is neither payload nor metadata is still listed — a phantom is
        // exactly the thing somebody needs to see and remove.
        $this->assertSame('other', $files['stray.tmp']['type']);
    }

    public function test_it_ignores_gitignore_scaffolding(): void
    {
        file_put_contents(storage_path('app/ringcentral/tosend/.gitignore'), '*');

        $this->assertSame([], (new FaxSpool)->files('ringcentral', 'tosend'));
    }

    public function test_it_deletes_a_file(): void
    {
        $path = storage_path('app/ringcentral/fail/IS21.cap');
        file_put_contents($path, 'x');

        $this->assertTrue((new FaxSpool)->delete('ringcentral', 'fail', 'IS21.cap', 'tester'));
        $this->assertFileDoesNotExist($path);
    }

    public function test_it_reports_a_file_that_is_already_gone(): void
    {
        $this->assertFalse((new FaxSpool)->delete('ringcentral', 'fail', 'never-existed.cap', 'tester'));
    }

    public function test_it_refuses_a_traversing_filename(): void
    {
        $outside = storage_path('app/ringcentral/sent/keepme.cap');
        file_put_contents($outside, 'x');

        // basename() reduces this to 'keepme.cap', which does not exist in fail/, so the
        // file in the sibling directory is untouched.
        $this->assertFalse((new FaxSpool)->delete('ringcentral', 'fail', '../sent/keepme.cap', 'tester'));
        $this->assertFileExists($outside);
    }

    public function test_it_refuses_an_unknown_provider(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FaxSpool)->files('nope', 'tosend');
    }

    public function test_it_refuses_an_unknown_folder(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new FaxSpool)->files('ringcentral', 'etc');
    }

    public function test_it_clears_a_folder(): void
    {
        foreach (['a.cap', 'b.fs', 'c.tmp'] as $name) {
            file_put_contents(storage_path("app/ringcentral/fail/{$name}"), 'x');
        }
        file_put_contents(storage_path('app/ringcentral/fail/.gitignore'), '*');

        $this->assertSame(3, (new FaxSpool)->clear('ringcentral', 'fail', 'tester'));
        $this->assertSame([], (new FaxSpool)->files('ringcentral', 'fail'));
        // Scaffolding survives.
        $this->assertFileExists(storage_path('app/ringcentral/fail/.gitignore'));
    }

    /**
     * A deleted .fs must stop being chased: the poller would otherwise keep checking a fax
     * whose files no longer exist until it timed out.
     */
    public function test_deleting_an_fs_resolves_its_pending_fax(): void
    {
        file_put_contents(storage_path('app/ringcentral/tosend/IS22.fs'), 'x');

        $pendingFax = PendingFax::create([
            'api_fax_id' => 'msg-9',
            'fax_provider' => 'ringcentral',
            'job_id' => 77,
            'fs_file_name' => 'IS22.fs',
            'cap_file' => 'IS22.cap',
            'filename' => 'IS22.cap',
            'phone' => '5551234567',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now(),
        ]);

        (new FaxSpool)->delete('ringcentral', 'tosend', 'IS22.fs', 'tester');

        $pendingFax->refresh();
        $this->assertSame('failed', $pendingFax->delivery_status);
        $this->assertNotNull($pendingFax->resolved_at);
    }

    /**
     * And the send job's unique lock must be released, or a fresh file of the same name
     * dropped by the fax service later would never be dispatched.
     */
    public function test_deleting_an_fs_releases_the_send_job_lock(): void
    {
        file_put_contents(storage_path('app/ringcentral/tosend/IS23.fs'), 'x');

        $lockKey = 'laravel_unique_job:'.SendFaxRingCentral::class.':IS23.fs';
        $this->assertTrue(Cache::lock($lockKey, 3600)->get());

        (new FaxSpool)->delete('ringcentral', 'tosend', 'IS23.fs', 'tester');

        // Acquirable again means the stale lock is gone.
        $this->assertTrue(Cache::lock($lockKey, 3600)->get());
    }

    public function test_it_normalizes_a_legacy_string_listing(): void
    {
        $normalized = FaxSpool::normalizeListing(['IS20.fs', 'IS20.cap', 'weird']);

        $this->assertSame('IS20.fs', $normalized[0]['name']);
        $this->assertSame('fs', $normalized[0]['type']);
        $this->assertSame('cap', $normalized[1]['type']);
        $this->assertSame('other', $normalized[2]['type']);
        $this->assertNull($normalized[0]['account']);
    }

    private function writeFs(string $name, int $jobId, string $capFile): void
    {
        file_put_contents(
            storage_path("app/ringcentral/tosend/{$name}"),
            '$var_def DATA5 "'.$jobId.'"'."\r\n".'$var_def DATA6 "'.$capFile.'"'
        );
    }

    private function emptyDir(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0775, true);

            return;
        }

        // Leave .gitignore alone — it is tracked scaffolding that keeps the otherwise
        // empty spool directories in the repository.
        foreach (array_diff(scandir($path), ['.', '..', '.gitignore']) as $file) {
            @unlink($path.$file);
        }
    }
}
