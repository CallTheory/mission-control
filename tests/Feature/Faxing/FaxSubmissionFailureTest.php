<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxFailureLog;
use App\Services\Faxing\FaxRetry;
use App\Services\Faxing\FaxSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

/**
 * A fax that never reached the provider has to leave a record.
 *
 * pending_faxes rows were only written once the provider returned 200, so a failed
 * submission produced an email and nothing else. The status pages list the provider's own
 * history, and a fax the provider never received is not in it — so whoever got the email
 * had nothing to look at and no way to send it again.
 */
class FaxSubmissionFailureTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        $this->root = storage_path('framework/testing/fail-'.uniqid());

        FaxSpoolSource::query()->where('key', 'ringcentral')->update(['root_path' => $this->root]);

        foreach (FaxSpool::allFolders() as $folder) {
            File::ensureDirectoryExists("{$this->root}/{$folder}");
        }
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        return [
            'jobID' => 4242,
            'capfile' => 'IS342.cap',
            'filename' => 'IS342.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS342.fs',
            'routing_reason' => 'spool source',
        ];
    }

    public function test_a_submission_failure_is_recorded_without_a_provider_id(): void
    {
        app(FaxFailureLog::class)->recordSubmissionFailure(
            $this->details(), 'ringcentral', 'ringcentral', 'Fax payload missing: IS342.cap'
        );

        $fax = PendingFax::sole();

        $this->assertSame('failed', $fax->delivery_status);
        $this->assertSame(FaxFailureLog::STAGE_SUBMISSION, $fax->failure_stage);
        $this->assertStringContainsString('payload missing', $fax->failure_reason);
        // The defining characteristic: the provider never gave us an id, because it never
        // saw the fax.
        $this->assertNull($fax->api_fax_id);
    }

    public function test_repeated_attempts_do_not_stack_up_rows(): void
    {
        foreach (range(1, 3) as $attempt) {
            app(FaxFailureLog::class)->recordSubmissionFailure(
                $this->details(), 'ringcentral', 'ringcentral', "attempt {$attempt}"
            );
        }

        $this->assertSame(1, PendingFax::count());
        $this->assertSame('attempt 3', PendingFax::sole()->failure_reason);
    }

    public function test_retrying_puts_the_spool_files_back_for_another_attempt(): void
    {
        File::put("{$this->root}/fail/IS342.fs", '$fax_status1 2');
        File::put("{$this->root}/fail/IS342.cap", 'payload');

        $fax = app(FaxFailureLog::class)->recordSubmissionFailure(
            $this->details(), 'ringcentral', 'ringcentral', 'boom'
        );

        app(FaxRetry::class)->retry($fax, 'tester');

        // Back where the scan will find them — nothing bespoke re-sends the fax, it just
        // goes through the ordinary pipeline again.
        $this->assertFileExists("{$this->root}/tosend/IS342.fs");
        $this->assertFileExists("{$this->root}/tosend/IS342.cap");
        $this->assertFileDoesNotExist("{$this->root}/fail/IS342.fs");

        // Stamped, so the same failure is not offered for retry twice.
        $this->assertNotNull($fax->fresh()->retried_at);
    }

    public function test_it_refuses_to_resend_a_fax_whose_payload_has_gone(): void
    {
        // The .fs alone would go back to tosend and fail on every scan for ever, which is
        // exactly the loop this work exists to stop. Intelligent Series shares one .cap
        // across several .fs files, so a sibling's move legitimately takes it away.
        File::put("{$this->root}/fail/IS342.fs", 'x');

        $fax = app(FaxFailureLog::class)->recordSubmissionFailure(
            $this->details(), 'ringcentral', 'ringcentral', 'Fax payload missing: IS342.cap'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/re-sent from Intelligent Series/');

        app(FaxRetry::class)->retry($fax);
    }

    public function test_retrying_without_the_spool_files_explains_itself(): void
    {
        $fax = app(FaxFailureLog::class)->recordSubmissionFailure(
            $this->details(), 'ringcentral', 'ringcentral', 'boom'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no longer in the failed folder/');

        app(FaxRetry::class)->retry($fax);
    }

    public function test_a_retried_failure_drops_off_the_list(): void
    {
        File::put("{$this->root}/fail/IS342.fs", 'x');
        File::put("{$this->root}/fail/IS342.cap", 'payload');

        $fax = app(FaxFailureLog::class)->recordSubmissionFailure(
            $this->details(), 'ringcentral', 'ringcentral', 'boom'
        );

        $this->assertSame(1, PendingFax::whereNull('retried_at')->where('delivery_status', 'failed')->count());

        app(FaxRetry::class)->retry($fax);

        // Otherwise the same fax could be sent a third and fourth time from the screen.
        $this->assertSame(0, PendingFax::whereNull('retried_at')->where('delivery_status', 'failed')->count());
    }
}
