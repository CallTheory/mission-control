<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\SendFaxRingCentral;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Models\RingCentralSender;
use App\Services\Faxing\FaxSpool;
use App\Services\Faxing\RingCentralRateLimit;
use App\Services\Faxing\RingCentralRateLimitState;
use App\Services\Faxing\RingCentralRetryDelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RingCentral\SDK\Http\ApiException;
use Tests\TestCase;
use Tests\Traits\FakesRingCentral;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Sending against RingCentral's rate limit, through one extension or a pool.
 *
 * One extension is the default and is simply a pool of one; every behaviour here applies
 * to it unchanged.
 */
class RingCentralRateLimitedSendingTest extends TestCase
{
    use FakesRingCentral;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('cloud-faxing');
        config([
            'app.switch_engine' => 'genesis',
            // Deterministic delays; jitter itself is covered by RingCentralRetryDelayTest.
            'services.fax.ringcentral.rate_limit_jitter_seconds' => 0,
            'services.fax.ringcentral.rate_limit_max_retries' => 3,
        ]);

        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_client_secret' => 'client-secret',
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => 'primary-jwt',
            'fax_failure_notification_email' => 'ops@example.test',
        ]);

        $this->root = storage_path('framework/testing/rc-pool-'.uniqid());
        FaxSpoolSource::query()->where('key', 'ringcentral')->update(['root_path' => $this->root]);

        foreach (FaxSpool::allFolders() as $folder) {
            File::ensureDirectoryExists("{$this->root}/{$folder}");
        }

        File::put("{$this->root}/tosend/IS20.cap", 'No messages');

        Queue::fake();
        Mail::fake();

        $this->fakeRingCentral(['primary-jwt' => '101', 'second-jwt' => '102', 'third-jwt' => '103']);

        $this->travelTo(now()->startOfMinute());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function job(): SendFaxRingCentral
    {
        return (new SendFaxRingCentral([
            'jobID' => 1131,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '3048222521',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
        ]))->withFakeQueueInteractions();
    }

    private function addSender(string $jwt, string $extensionId): RingCentralSender
    {
        return RingCentralSender::create(['label' => "Ext {$extensionId}", 'jwt_token' => $jwt, 'extension_id' => $extensionId]);
    }

    private function state(): RingCentralRateLimitState
    {
        return app(RingCentralRateLimitState::class);
    }

    /**
     * @return list<string> the access token each request to RingCentral carried
     */
    private function tokensUsed(): array
    {
        return array_map(
            fn (array $entry) => preg_replace('/^bearer\s+/i', '', $entry['request']->getHeaderLine('Authorization')),
            $this->ringCentralRequests
        );
    }

    // --- One extension (the default) ---------------------------------------------------

    public function test_one_extension_sends_exactly_as_before(): void
    {
        $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 9));

        $job = $this->job();
        $job->handle();

        $job->assertNotReleased();
        $this->assertSame(['access-101'], $this->tokensUsed());

        $fax = PendingFax::sole();
        $this->assertSame('pending', $fax->delivery_status);
        // Null for the primary, the same as every row written before pools existed.
        $this->assertNull($fax->ringcentral_sender);
        $this->assertSame('101', $fax->ringcentral_extension_id);
    }

    public function test_a_send_that_used_the_last_request_holds_the_next_fax_without_calling_ringcentral(): void
    {
        $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 0, window: 60));
        $this->job()->handle();

        $next = $this->job();
        $next->handle();

        $next->assertReleased(delay: 60);
        // Exactly one request: the held fax never reached RingCentral, so it could not
        // restart the penalty clock.
        $this->assertCount(1, $this->ringCentralRequests);
    }

    public function test_the_hold_follows_a_raised_limit_window_from_the_headers(): void
    {
        $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 0, limit: 40, window: 120));
        $this->job()->handle();

        $next = $this->job();
        $next->handle();

        $next->assertReleased(delay: 120);
    }

    public function test_a_429_is_retried_after_retry_after(): void
    {
        $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 75));

        $job = $this->job();
        $job->handle();

        $job->assertReleased(delay: 75);
        $job->assertNotFailed();
        $this->assertSame(0, PendingFax::count());
    }

    public function test_a_429_without_retry_after_waits_for_the_window(): void
    {
        $this->queueRingCentralResponse(429, ['X-Rate-Limit-Window' => '60']);

        $job = $this->job();
        $job->handle();

        $job->assertReleased(delay: 60);
    }

    public function test_a_429_without_any_rate_limit_headers_uses_the_documented_fallback(): void
    {
        $this->queueRingCentralResponse(429);

        $job = $this->job();
        $job->handle();

        $job->assertReleased(delay: RingCentralRetryDelay::FALLBACK_SECONDS);
    }

    public function test_a_fax_rate_limited_past_the_cap_fails_through_the_usual_handling(): void
    {
        foreach (range(1, 3) as $attempt) {
            $this->state()->forget('primary');
            $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 10));

            $job = $this->job();
            $job->handle();
            $job->assertReleased();
        }

        $this->state()->forget('primary');
        $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 10));

        $last = $this->job();
        $last->handle();

        $last->assertFailed();
        $last->assertNotReleased();
    }

    public function test_a_success_resets_the_rate_limited_count(): void
    {
        $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 10));
        $this->job()->handle();

        $this->state()->forget('primary');
        $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 5));
        $this->job()->handle();

        $this->assertSame(1, $this->state()->countRateLimitedAttempt($this->job()->uniqueId(), 60));
    }

    public function test_a_failure_that_is_not_a_rate_limit_is_unchanged(): void
    {
        $this->queueRingCentralResponse(503, $this->rateLimitHeaders(remaining: 4));

        $job = $this->job();

        $this->expectException(ApiException::class);

        try {
            $job->handle();
        } finally {
            // Rethrown for the queue's own retry (maxExceptions / backoff), not released
            // on a rate-limit delay.
            $job->assertNotReleased();
            $this->assertNull($this->state()->availableAt('primary'));
        }
    }

    // --- A pool of extensions -------------------------------------------------------------

    public function test_a_locked_out_extension_is_passed_over_for_one_with_requests_left(): void
    {
        $this->addSender('second-jwt', '102');
        $this->state()->record('primary', new RingCentralRateLimit(status: 429, retryAfter: 60));
        $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 9));

        $job = $this->job();
        $job->handle();

        $job->assertNotReleased();
        $this->assertSame(['access-102'], $this->tokensUsed());

        $fax = PendingFax::sole();
        $this->assertSame(RingCentralSender::first()->senderKey(), $fax->ringcentral_sender);
        $this->assertSame('102', $fax->ringcentral_extension_id);
    }

    public function test_when_every_extension_is_out_the_fax_waits_for_the_first_lockout_to_end(): void
    {
        $this->addSender('second-jwt', '102');
        $this->state()->record('primary', new RingCentralRateLimit(status: 429, retryAfter: 50));
        $this->state()->record(RingCentralSender::first()->senderKey(), new RingCentralRateLimit(status: 200, remaining: 0, window: 30));

        $job = $this->job();
        $job->handle();

        $job->assertReleased(delay: 30);
        $this->assertSame([], $this->ringCentralRequests);
    }

    public function test_a_429_on_one_extension_retries_promptly_when_another_has_requests(): void
    {
        $this->addSender('second-jwt', '102');
        $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 60));

        $job = $this->job();
        $job->handle();

        // Not the 60s lockout: that applies only to the extension that was rejected.
        $job->assertReleased(delay: 1);
    }

    public function test_sends_rotate_across_the_pool(): void
    {
        $this->addSender('second-jwt', '102');
        $this->addSender('third-jwt', '103');

        foreach (range(1, 3) as $ignored) {
            $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 9));
            $this->job()->handle();
        }

        $this->assertEqualsCanonicalizing(['access-101', 'access-102', 'access-103'], $this->tokensUsed());
    }

    public function test_a_disabled_sender_is_not_sent_through(): void
    {
        $this->addSender('second-jwt', '102')->update(['enabled' => false]);

        foreach (range(1, 3) as $ignored) {
            $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 9));
            $this->job()->handle();
        }

        $this->assertSame(['access-101', 'access-101', 'access-101'], $this->tokensUsed());
    }
}
