<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\SendFaxRingCentral;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\PendingFax;
use App\Services\Faxing\FaxSpool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RingCentral\SDK\Http\ApiException;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\FakesRingCentral;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Every RingCentral fax response has to leave its rate-limit headers in the log.
 *
 * This is the evidence for the 9:00 failures: without it there is no way to tell a 429
 * from any other error, which extension was out of requests, or what limit RingCentral is
 * really applying to the app.
 */
class RingCentralRateLimitLoggingTest extends TestCase
{
    use FakesRingCentral;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('cloud-faxing');
        config(['app.switch_engine' => 'genesis']);

        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_client_secret' => 'client-secret',
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => 'primary-jwt',
            'fax_failure_notification_email' => 'ops@example.test',
        ]);

        $this->root = storage_path('framework/testing/rc-log-'.uniqid());
        FaxSpoolSource::query()->where('key', 'ringcentral')->update(['root_path' => $this->root]);

        foreach (FaxSpool::allFolders() as $folder) {
            File::ensureDirectoryExists("{$this->root}/{$folder}");
        }

        File::put("{$this->root}/tosend/IS20.cap", 'No messages');

        Queue::fake();
        Mail::fake();
        Log::spy();

        $this->fakeRingCentral();
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

    public function test_a_successful_send_logs_its_rate_limit_headers_and_extension(): void
    {
        $this->queueRingCentralResponse(200, $this->rateLimitHeaders(remaining: 7));

        $this->job()->handle();

        Log::shouldHaveReceived('info')->with('RingCentral fax API response', Mockery::on(fn (array $context) => $context['http_status'] === 200
            && $context['rate_limit_group'] === 'heavy'
            && $context['rate_limit_limit'] === 10
            && $context['rate_limit_remaining'] === 7
            && $context['rate_limit_window'] === 60
            && $context['retry_after'] === null
            && $context['extension_id'] === '101'
            && $context['jobID'] === 1131
            && $context['fsFileName'] === 'IS20.fs'
            && $context['account'] === '1544'))->once();

        $this->assertSame(1, PendingFax::count());
    }

    public function test_a_throttled_send_logs_its_headers_including_retry_after(): void
    {
        $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 37));

        $this->job()->handle();

        Log::shouldHaveReceived('warning')->with('RingCentral fax API response', Mockery::on(fn (array $context) => $context['http_status'] === 429
            && $context['rate_limit_remaining'] === 0
            && $context['retry_after'] === 37
            && $context['extension_id'] === '101'))->once();
    }

    public function test_a_response_without_rate_limit_headers_still_logs(): void
    {
        $this->queueRingCentralResponse(200);

        $this->job()->handle();

        Log::shouldHaveReceived('info')->with('RingCentral fax API response', Mockery::on(fn (array $context) => $context['http_status'] === 200
            && $context['rate_limit_remaining'] === null))->once();
    }

    public function test_a_failure_record_keeps_the_http_status(): void
    {
        $this->queueRingCentralResponse(503);

        $job = $this->job();

        try {
            $job->handle();
            $this->fail('A 503 should have been rethrown for the queue to retry.');
        } catch (ApiException $e) {
            $job->failed($e);
        }

        $this->assertStringStartsWith('HTTP 503: ', (string) PendingFax::sole()->failure_reason);
    }

    public function test_a_non_api_failure_is_recorded_unchanged(): void
    {
        $this->job()->failed(new RuntimeException('Fax payload missing: IS20.cap'));

        $this->assertSame('Fax payload missing: IS20.cap', PendingFax::sole()->failure_reason);
    }
}
