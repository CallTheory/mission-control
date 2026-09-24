<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\ScanFaxSpoolLane;
use App\Jobs\SendFaxJob;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxRouter;
use App\Services\Faxing\FaxSourceHealth;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\FsFileParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

class ScanFaxSpoolLaneTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private string $rootA;

    private string $rootB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create(['mfax_api_key' => encrypt('test-api-key')]);
        $this->enableSystemFeature('cloud-faxing');

        $this->rootA = storage_path('framework/testing/fax-a-'.uniqid());
        $this->rootB = storage_path('framework/testing/fax-b-'.uniqid());

        foreach (['isa' => $this->rootA, 'isb' => $this->rootB] as $key => $root) {
            FaxSpoolSource::create(['key' => $key, 'name' => strtoupper($key), 'root_path' => $root]);

            foreach (FaxSpool::allFolders() as $folder) {
                File::ensureDirectoryExists("{$root}/{$folder}");
            }
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->rootA);
        File::deleteDirectory($this->rootB);

        parent::tearDown();
    }

    private function writeFs(string $root, string $name, int $jobId = 4242): void
    {
        // The payload goes down too: Intelligent Series always writes the pair, and a
        // .fs without its .cap is the orphan case that gets quarantined.
        File::put("{$root}/tosend/IS20.cap", 'payload');

        File::put("{$root}/tosend/{$name}", implode("\r\n", [
            '$var_def DATA5 "'.$jobId.'"',
            '$var_def DATA6 "c:\\copia\\tosend\\IS20.cap"',
            '$fax_filename c:\\copia\\tosend\\IS20.cap',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]));
    }

    public function test_it_submits_a_valid_fax_with_its_own_source(): void
    {
        Bus::fake();
        $this->writeFs($this->rootA, 'IS20.fs');

        (new ScanFaxSpoolLane('isa'))->handle(
            app(FsFileParser::class),
            new FaxSpool,
            app(FaxSourceHealth::class),
            app(FaxRouter::class),
        );

        Bus::assertDispatched(SendFaxJob::class, fn (SendFaxJob $job) => $job->sourceKey() === 'isa'
            && $job->fsFileName === 'IS20.fs');
    }

    public function test_two_sources_with_the_same_fs_name_both_submit(): void
    {
        // The core multi-server regression. Both Intelligent Series servers issue IS20.fs;
        // if either the dedupe or the unique lock ignored the source, one fax would be
        // silently dropped and its file left to rot in tosend.
        Bus::fake();
        $this->writeFs($this->rootA, 'IS20.fs');
        $this->writeFs($this->rootB, 'IS20.fs');

        $this->runLane('isa');
        $this->runLane('isb');

        Bus::assertDispatchedTimes(SendFaxJob::class, 2);
    }

    public function test_a_fax_already_pending_in_this_source_is_not_resubmitted(): void
    {
        Bus::fake();
        $this->writeFs($this->rootA, 'IS20.fs');

        PendingFax::create([
            'api_fax_id' => 'x',
            'fax_provider' => 'mfax',
            'spool_source_key' => 'isa',
            'job_id' => 4242,
            'fs_file_name' => 'IS20.fs',
            'cap_file' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'original_status' => '2',
            'delivery_status' => 'pending',
        ]);

        $this->runLane('isa');

        Bus::assertNotDispatched(SendFaxJob::class);
    }

    public function test_another_sources_pending_fax_does_not_suppress_this_one(): void
    {
        Bus::fake();
        $this->writeFs($this->rootB, 'IS20.fs');

        // Same .fs name, different server, still awaiting delivery confirmation.
        PendingFax::create([
            'api_fax_id' => 'x',
            'fax_provider' => 'mfax',
            'spool_source_key' => 'isa',
            'job_id' => 4242,
            'fs_file_name' => 'IS20.fs',
            'cap_file' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'original_status' => '2',
            'delivery_status' => 'pending',
        ]);

        $this->runLane('isb');

        Bus::assertDispatchedTimes(SendFaxJob::class, 1);
    }

    public function test_an_unreachable_source_is_recorded_without_touching_other_lanes(): void
    {
        Bus::fake();
        FaxSpoolSource::query()->where('key', 'isa')->update(['root_path' => '/nonexistent/spool']);
        $this->writeFs($this->rootB, 'IS20.fs');

        $this->runLane('isa');

        // Recorded and rested...
        $health = app(FaxSourceHealth::class);
        $this->assertSame(1, $health->status('isa')['failures']);
        $this->assertTrue($health->inCooldown('isa'));

        // ...and the other server's faxes are entirely unaffected. Nothing is redirected:
        // the dead source's files stay where they are.
        $this->runLane('isb');
        Bus::assertDispatchedTimes(SendFaxJob::class, 1);
        $this->assertFalse($health->inCooldown('isb'));
    }

    public function test_a_successful_scan_clears_a_previous_failure(): void
    {
        Bus::fake();
        $health = app(FaxSourceHealth::class);
        $health->markFailed('isa', 'was unreachable');

        $this->runLane('isa');

        $this->assertSame(0, $health->status('isa')['failures']);
        $this->assertFalse($health->inCooldown('isa'));
    }

    public function test_an_empty_spool_is_a_success_not_a_fault(): void
    {
        // Only one IS server processes faxes at a time; the others are healthy and idle.
        Bus::fake();

        $this->runLane('isa');

        $this->assertSame(0, app(FaxSourceHealth::class)->status('isa')['failures']);
        Bus::assertNothingDispatched();
    }

    public function test_a_persistently_invalid_fs_is_quarantined(): void
    {
        Bus::fake();
        File::put("{$this->rootA}/tosend/BROKEN.fs", 'nothing parseable here');
        touch("{$this->rootA}/tosend/BROKEN.fs", time() - 300);

        $this->runLane('isa');

        $this->assertFileDoesNotExist("{$this->rootA}/tosend/BROKEN.fs");
        $this->assertFileExists("{$this->rootA}/fail/BROKEN.fs");
    }

    public function test_a_freshly_written_invalid_fs_is_left_alone(): void
    {
        // The grace window stops us catching the fax service mid-write.
        Bus::fake();
        File::put("{$this->rootA}/tosend/BROKEN.fs", 'partial');

        $this->runLane('isa');

        $this->assertFileExists("{$this->rootA}/tosend/BROKEN.fs");
    }

    public function test_one_bad_file_does_not_abandon_the_rest_of_the_lane(): void
    {
        Bus::fake();
        File::put("{$this->rootA}/tosend/BROKEN.fs", 'unparseable');
        $this->writeFs($this->rootA, 'IS21.fs');

        $this->runLane('isa');

        Bus::assertDispatchedTimes(SendFaxJob::class, 1);
    }

    public function test_an_fs_whose_payload_has_gone_is_quarantined_not_retried(): void
    {
        // The production loop: Intelligent Series fans one .cap out to several .fs files,
        // a sibling's move removes the shared payload, and the orphan left behind failed
        // and emailed on every scan for ever because the job that would move it aside was
        // itself suppressed by a stranded lock.
        Bus::fake();
        $this->writeFs($this->rootA, 'IS368.fs');
        File::delete("{$this->rootA}/tosend/IS20.cap");
        touch("{$this->rootA}/tosend/IS368.fs", time() - 300);

        $this->runLane('isa');

        Bus::assertNotDispatched(SendFaxJob::class);
        $this->assertFileDoesNotExist("{$this->rootA}/tosend/IS368.fs");
        $this->assertFileExists("{$this->rootA}/fail/IS368.fs");
    }

    public function test_an_orphan_is_recorded_so_it_can_be_seen(): void
    {
        Bus::fake();
        $this->writeFs($this->rootA, 'IS368.fs');
        File::delete("{$this->rootA}/tosend/IS20.cap");
        touch("{$this->rootA}/tosend/IS368.fs", time() - 300);

        $this->runLane('isa');

        $fax = PendingFax::sole();
        $this->assertSame('failed', $fax->delivery_status);
        $this->assertStringContainsString('payload missing', $fax->failure_reason);
    }

    public function test_a_payload_written_moments_later_is_left_alone(): void
    {
        // The .fs can land a moment before its .cap; quarantining immediately would throw
        // away a fax that was about to be perfectly sendable.
        Bus::fake();
        $this->writeFs($this->rootA, 'IS368.fs');
        File::delete("{$this->rootA}/tosend/IS20.cap");

        $this->runLane('isa');

        $this->assertFileExists("{$this->rootA}/tosend/IS368.fs");
    }

    private function runLane(string $sourceKey): void
    {
        (new ScanFaxSpoolLane($sourceKey))->handle(
            app(FsFileParser::class),
            new FaxSpool,
            app(FaxSourceHealth::class),
            app(FaxRouter::class),
        );
    }
}
