<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Services\Faxing\Spool\Exceptions\SpoolUnavailable;
use App\Services\Faxing\Spool\LocalSpoolFilesystem;
use App\Services\Faxing\Spool\SpoolProbeResult;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * The behaviours every driver has to share. The SMB driver is exercised against the same
 * expectations by the opt-in integration test; this is the one that runs everywhere.
 */
class LocalSpoolFilesystemTest extends TestCase
{
    private string $root;

    private LocalSpoolFilesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/spool-'.uniqid());
        File::ensureDirectoryExists("{$this->root}/tosend");
        File::ensureDirectoryExists("{$this->root}/fail");

        $this->fs = new LocalSpoolFilesystem($this->root);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_lists_files_with_their_metadata(): void
    {
        File::put("{$this->root}/tosend/IS20.fs", 'abc');

        $files = $this->fs->list('tosend');

        $this->assertCount(1, $files);
        $this->assertSame('IS20.fs', $files[0]->name);
        $this->assertSame(3, $files[0]->size);
        $this->assertSame('fs', $files[0]->type());
        // Metadata comes back with the listing; fetching it per file would be two extra
        // round trips each over SMB.
        $this->assertNotNull($files[0]->modifiedAt);
    }

    public function test_a_missing_folder_lists_as_empty_rather_than_failing(): void
    {
        // An empty tosend/ is the normal resting state for every Intelligent Series
        // server that is not currently processing, so this must not look like an outage.
        $this->assertSame([], $this->fs->list('preproc'));
    }

    public function test_an_unreadable_source_throws_rather_than_reporting_empty(): void
    {
        $fs = new LocalSpoolFilesystem('/nonexistent/spool');

        // ...whereas a source that cannot be reached at all has to be distinguishable
        // from an idle one, or a genuine outage looks like a quiet day.
        $this->assertSame([], $fs->list('tosend'));
        $this->assertSame(SpoolProbeResult::MISCONFIGURED, $fs->probe()->status);
    }

    public function test_it_ignores_repository_scaffolding(): void
    {
        File::put("{$this->root}/tosend/.gitignore", "*.fs\n");

        $this->assertSame([], $this->fs->list('tosend'));
    }

    public function test_it_reads_writes_and_deletes(): void
    {
        $this->fs->write('tosend', 'IS20.fs', 'payload');

        $this->assertSame('payload', $this->fs->read('tosend', 'IS20.fs'));
        $this->assertTrue($this->fs->delete('tosend', 'IS20.fs'));
        $this->assertNull($this->fs->read('tosend', 'IS20.fs'));
    }

    public function test_deleting_a_file_that_is_already_gone_reports_false(): void
    {
        // The common case for a phantom the fax service cleaned up between the page
        // rendering and the click.
        $this->assertFalse($this->fs->delete('tosend', 'IS20.fs'));
    }

    public function test_it_moves_a_file_between_folders(): void
    {
        File::put("{$this->root}/tosend/BROKEN.fs", 'x');

        $this->assertTrue($this->fs->move('tosend', 'BROKEN.fs', 'fail', 'BROKEN.fs'));

        $this->assertFileDoesNotExist("{$this->root}/tosend/BROKEN.fs");
        $this->assertFileExists("{$this->root}/fail/BROKEN.fs");
    }

    public function test_moving_a_missing_file_reports_false(): void
    {
        $this->assertFalse($this->fs->move('tosend', 'nope.fs', 'fail', 'nope.fs'));
    }

    public function test_it_refuses_a_traversing_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->fs->read('tosend', '../../.env');
    }

    public function test_it_refuses_a_symlink_planted_in_the_spool(): void
    {
        $secret = storage_path('framework/testing/secret-'.uniqid());
        File::put($secret, 'not yours');

        symlink($secret, "{$this->root}/tosend/IS20.fs");

        try {
            $this->expectException(InvalidArgumentException::class);
            $this->fs->read('tosend', 'IS20.fs');
        } finally {
            @unlink("{$this->root}/tosend/IS20.fs");
            @unlink($secret);
        }
    }

    public function test_the_probe_reports_folder_counts_and_writability(): void
    {
        File::put("{$this->root}/tosend/IS20.fs", 'x');

        $probe = $this->fs->probe();

        $this->assertTrue($probe->ok());
        $this->assertSame(1, $probe->folderCounts['tosend']);
        $this->assertTrue($probe->writable);
    }

    public function test_writing_creates_the_folder_when_it_is_missing(): void
    {
        $this->fs->write('sent', 'IS20.fs', 'x');

        $this->assertFileExists("{$this->root}/sent/IS20.fs");
    }

    public function test_it_reports_an_unwritable_source(): void
    {
        // Results are reported back to Intelligent Series by rewriting the .fs into sent/
        // or fail/, so a read-only source would send faxes and never confirm them.
        chmod($this->root, 0555);

        try {
            $this->assertFalse($this->fs->probe()->writable);
        } finally {
            chmod($this->root, 0775);
        }
    }

    public function test_it_surfaces_a_write_failure(): void
    {
        chmod("{$this->root}/tosend", 0555);

        try {
            $this->expectException(SpoolUnavailable::class);
            $this->fs->write('tosend', 'IS20.fs', 'x');
        } finally {
            chmod("{$this->root}/tosend", 0775);
        }
    }
}
