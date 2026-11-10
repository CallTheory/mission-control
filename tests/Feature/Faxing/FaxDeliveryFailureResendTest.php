<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\ScanFaxSpoolLane;
use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxRingCentral;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxDeliveryResolver;
use App\Services\Faxing\FaxRouter;
use App\Services\Faxing\FaxSourceHealth;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\FsFileParser;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * A delivery failure must not be submitted a second time while its files are still
 * waiting to be moved to fail/.
 *
 * In production the poller marked IS6707.fs failed at 14:00:03. The scan ran before
 * MoveFailedFaxFiles did, found the .fs still in tosend/ with no live row, and dispatched
 * it again. The move then took the payload, so the resend failed with "Fax payload
 * missing" three seconds later. Other faxes in the same run were actually sent twice.
 */
class FaxDeliveryFailureResendTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'fax_failure_notification_email' => 'test@example.com',
        ]);
        $this->enableSystemFeature('cloud-faxing');

        $this->root = storage_path('framework/testing/fax-resend-'.uniqid());
        FaxSpoolSource::create(['key' => 'isa', 'name' => 'ISA', 'root_path' => $this->root]);

        foreach (FaxSpool::allFolders() as $folder) {
            File::ensureDirectoryExists("{$this->root}/{$folder}");
        }

        File::put("{$this->root}/tosend/IS20.cap", 'payload');
        File::put("{$this->root}/tosend/IS20.fs", implode("\r\n", [
            '$var_def DATA5 "4242"',
            '$var_def DATA6 "c:\\copia\\tosend\\IS20.cap"',
            '$fax_filename c:\\copia\\tosend\\IS20.cap',
            '$fax_phone 9139069098',
            '$fax_status1 2',
        ]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function pendingFax(array $overrides = []): PendingFax
    {
        return PendingFax::create($overrides + [
            'api_fax_id' => 'mfax-1',
            'fax_provider' => 'mfax',
            'spool_source_key' => 'isa',
            'job_id' => 4242,
            'fs_file_name' => 'IS20.fs',
            'cap_file' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'original_status' => '2',
            'delivery_status' => 'pending',
            'submitted_at' => now()->subMinutes(15),
        ]);
    }

    private function scan(): void
    {
        (new ScanFaxSpoolLane('isa'))->handle(
            app(FsFileParser::class),
            new FaxSpool,
            app(FaxSourceHealth::class),
            app(FaxRouter::class),
        );
    }

    public function test_a_failure_whose_files_have_not_moved_yet_is_not_submitted_again(): void
    {
        Bus::fake();
        Mail::fake();
        $fax = $this->pendingFax();

        app(FaxDeliveryResolver::class)->resolve($fax, 'failed', 'MFax status: failed');

        // The move is queued but has not run; the .fs is still in tosend/.
        $this->assertFileExists("{$this->root}/tosend/IS20.fs");
        $this->assertSame('failing', $fax->refresh()->delivery_status);

        $this->scan();

        Bus::assertNotDispatched(SendFaxJob::class);
        Mail::assertQueued(FaxFailAlert::class);
    }

    public function test_the_move_marks_the_row_failed_once_the_files_are_out_of_tosend(): void
    {
        $fax = $this->pendingFax(['delivery_status' => 'failing']);

        (new MoveFailedFaxFiles([
            'jobID' => 4242, 'capfile' => 'IS20.cap', 'filename' => 'IS20.cap', 'phone' => '9139069098',
            'status' => '2', 'fsFileName' => 'IS20.fs',
        ], 'mfax', 'isa', $fax->id))->handle();

        $this->assertFileDoesNotExist("{$this->root}/tosend/IS20.fs");
        $this->assertFileExists("{$this->root}/fail/IS20.fs");
        $this->assertSame('failed', $fax->refresh()->delivery_status);
    }

    public function test_the_move_leaves_a_row_that_is_not_failing_alone(): void
    {
        // A move for a submission failure, or one queued before this change, carries no
        // row id; one that does must still not overwrite a row that has moved on.
        $fax = $this->pendingFax(['delivery_status' => 'pending']);

        (new MoveFailedFaxFiles([
            'jobID' => 4242, 'capfile' => 'IS20.cap', 'filename' => 'IS20.cap', 'phone' => '9139069098',
            'status' => '2', 'fsFileName' => 'IS20.fs',
        ], 'mfax', 'isa', $fax->id))->handle();

        $this->assertSame('pending', $fax->refresh()->delivery_status);
    }

    public function test_after_the_move_a_fax_put_back_with_send_again_is_submitted(): void
    {
        Bus::fake();
        $fax = $this->pendingFax(['delivery_status' => 'failed', 'retried_at' => now()]);

        $this->scan();

        Bus::assertDispatched(SendFaxJob::class);
        $this->assertSame('failed', $fax->refresh()->delivery_status);
    }

    public function test_a_stalled_move_is_dispatched_again(): void
    {
        Bus::fake();
        $stalled = $this->pendingFax(['delivery_status' => 'failing', 'failure_stage' => 'delivery', 'resolved_at' => now()->subMinutes(10)]);
        $this->pendingFax(['delivery_status' => 'failing', 'failure_stage' => 'delivery', 'resolved_at' => now()->subSeconds(30), 'fs_file_name' => 'IS21.fs']);
        // Possibly failing over to the other provider, so its files must stay put.
        $this->pendingFax(['delivery_status' => 'failing', 'failure_stage' => 'submission', 'api_fax_id' => null, 'resolved_at' => now()->subMinutes(10), 'fs_file_name' => 'IS22.fs']);

        $this->assertSame(1, app(FaxDeliveryResolver::class)->retryStalledMoves(300));

        Bus::assertDispatchedTimes(MoveFailedFaxFiles::class, 1);
        Bus::assertDispatched(MoveFailedFaxFiles::class, fn (MoveFailedFaxFiles $job) => $job->pendingFaxId === $stalled->id
            && $job->fsFileName === 'IS20.fs'
            && $job->sourceKey() === 'isa');
    }

    public function test_a_failure_records_why(): void
    {
        Bus::fake();
        Mail::fake();
        $fax = $this->pendingFax();

        app(FaxDeliveryResolver::class)->resolve($fax, 'failed', 'RingCentral status: SendingFailed (CallFailed)');

        $fax->refresh();
        $this->assertSame('delivery', $fax->failure_stage);
        $this->assertSame('RingCentral status: SendingFailed (CallFailed)', $fax->failure_reason);
        $this->assertNotNull($fax->resolved_at);
    }

    public function test_a_submission_failure_is_not_submitted_again_before_its_files_move(): void
    {
        Bus::fake();
        Mail::fake();
        $this->cacheAccount();

        (new SendFaxRingCentral($this->isfax()))->failed(new Exception('HTTP 503: Service Unavailable'));

        $row = PendingFax::sole();
        $this->assertSame('failing', $row->delivery_status);
        $this->assertSame('submission', $row->failure_stage);
        Bus::assertDispatched(MoveFailedFaxFiles::class, fn (MoveFailedFaxFiles $job) => $job->pendingFaxId === $row->id);

        $this->scan();

        Bus::assertNotDispatched(SendFaxJob::class);
        Bus::assertNotDispatched(SendFaxRingCentral::class);
    }

    public function test_the_mfax_sender_hands_its_failure_row_to_the_move_too(): void
    {
        Bus::fake();
        Mail::fake();
        $this->cacheAccount();

        (new SendFaxJob($this->isfax()))->failed(new Exception('mFax said no'));

        $row = PendingFax::sole();
        $this->assertSame('failing', $row->delivery_status);
        Bus::assertDispatched(MoveFailedFaxFiles::class, fn (MoveFailedFaxFiles $job) => $job->pendingFaxId === $row->id);
    }

    public function test_a_later_attempt_updates_the_same_failure_row(): void
    {
        Bus::fake();
        Mail::fake();
        $this->cacheAccount();

        (new SendFaxRingCentral($this->isfax()))->failed(new Exception('first'));
        PendingFax::sole()->update(['delivery_status' => 'failed']);
        (new SendFaxRingCentral($this->isfax()))->failed(new Exception('second'));

        $row = PendingFax::sole();
        $this->assertSame('failing', $row->delivery_status);
        $this->assertSame('second', $row->failure_reason);
    }

    /**
     * The send jobs label the alert with the Intelligent Series account. That lookup is
     * cached in Redis, which the sandbox lacks, and a miss would wait on the IS database.
     */
    private function cacheAccount(): void
    {
        Redis::shouldReceive('get')->andReturn(json_encode(['number' => '1509', 'name' => 'Test Client']));
        Redis::shouldReceive('setex', 'set', 'del')->andReturn(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function isfax(): array
    {
        return [
            'jobID' => 4242,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
            'source_key' => 'isa',
            'routing_reason' => 'spool source',
            'allow_failover' => false,
        ];
    }

    public function test_the_ringcentral_reason_carries_each_recipients_error_code(): void
    {
        $this->assertSame(
            'RingCentral status: SendingFailed (CallFailed)',
            FaxDeliveryResolver::ringCentralReason('RingCentral status', 'SendingFailed', [(object) ['faxErrorCode' => 'CallFailed']])
        );
        $this->assertSame(
            'RingCentral webhook status: DeliveryFailed (LineBusy, NoAnswer)',
            FaxDeliveryResolver::ringCentralReason('RingCentral webhook status', 'DeliveryFailed', [
                ['faxErrorCode' => 'LineBusy'], ['faxErrorCode' => 'NoAnswer'], ['faxErrorCode' => 'LineBusy'],
            ])
        );
        $this->assertSame(
            'RingCentral status: SendingFailed',
            FaxDeliveryResolver::ringCentralReason('RingCentral status', 'SendingFailed', null)
        );
    }
}
