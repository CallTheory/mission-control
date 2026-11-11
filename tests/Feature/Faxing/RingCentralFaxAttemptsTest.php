<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Livewire\Utilities\CloudFaxingRingCentral;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\RingCentralSender;
use App\Models\User;
use App\Services\Faxing\RingCentralFaxAttempts;
use App\Services\Faxing\RingCentralRateLimit;
use App\Services\Faxing\RingCentralRateLimitState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\FakesRingCentral;

/**
 * Details on a failed RingCentral fax: the calls RingCentral made trying to deliver it,
 * from its call log, cached in Redis.
 */
class RingCentralFaxAttemptsTest extends TestCase
{
    use FakesRingCentral;
    use RefreshDatabase;

    private const CACHE_KEY = 'cloud-faxing:ringcentral:call-log:3280458140006';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_client_secret' => 'client-secret',
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => 'primary-jwt',
        ]);

        $this->fakeRingCentral(['primary-jwt' => '101', 'second-jwt' => '102']);
    }

    private function failedFax(array $overrides = []): PendingFax
    {
        return PendingFax::create($overrides + [
            'api_fax_id' => '3280458140006',
            'fax_provider' => 'ringcentral',
            'spool_source_key' => 'ringcentral',
            'job_id' => 34635,
            'fs_file_name' => 'IS6707.fs',
            'cap_file' => 'IS6707.cap',
            'filename' => 'IS6707.cap',
            'phone' => '3042639659',
            'original_status' => '2',
            'delivery_status' => 'failed',
            'failure_stage' => 'delivery',
            'failure_reason' => 'RingCentral status: SendingFailed (CallFailed)',
            'submitted_at' => '2026-11-10 13:46:05',
            'resolved_at' => '2026-11-10 14:00:03',
        ]);
    }

    /**
     * The real call log for IS6707, plus the earlier fax's last attempt to the same
     * number, which falls inside the same window and must not be counted.
     */
    private function queueCallLog(array $headers = []): void
    {
        $call = fn (string $start, int $duration, string $messageId) => [
            'startTime' => $start,
            'duration' => $duration,
            'type' => 'Fax',
            'direction' => 'Outbound',
            'result' => 'Fax Not Sent',
            'message' => ['id' => $messageId, 'type' => 'Fax'],
            'legs' => [['legType' => 'FaxSent', 'result' => 'Fax Not Sent', 'reason' => 'Fax Not Sent']],
        ];

        $this->queueRingCentralResponse(200, $headers, ['records' => [
            $call('2026-11-10T13:58:33.344Z', 63, '3280458140006'),
            $call('2026-11-10T13:52:35.071Z', 64, '3280458140006'),
            $call('2026-11-10T13:46:43.223Z', 64, '3280458140006'),
            $call('2026-11-10T13:40:35.064Z', 69, '3280400000006'),
        ]]);
    }

    public function test_it_lists_only_this_faxs_calls_oldest_first_with_a_reading_of_them(): void
    {
        $this->queueCallLog();

        $details = app(RingCentralFaxAttempts::class)->for($this->failedFax());

        $this->assertNull($details['error']);
        $this->assertSame(['2026-11-10T13:46:43.223Z', '2026-11-10T13:52:35.071Z', '2026-11-10T13:58:33.344Z'], array_column($details['attempts'], 'started_at'));
        $this->assertSame([64, 64, 63], array_column($details['attempts'], 'duration'));
        // The leg's reason only repeats the result, so it is not shown twice.
        $this->assertSame([null, null, null], array_column($details['attempts'], 'reason'));
        $this->assertStringContainsString('All 3 calls were answered', (string) $details['hint']);

        $query = [];
        parse_str($this->ringCentralRequests[0]['request']->getUri()->getQuery(), $query);
        $this->assertStringEndsWith('/extension/~/call-log', $this->ringCentralRequests[0]['request']->getUri()->getPath());
        $this->assertSame('+13042639659', $query['phoneNumber']);
        $this->assertSame('Fax', $query['type']);
        $this->assertSame('2026-11-10T13:44:05.000Z', $query['dateFrom']);
    }

    public function test_the_result_is_cached_in_redis_and_reused(): void
    {
        $this->queueCallLog();
        $fax = $this->failedFax();

        app(RingCentralFaxAttempts::class)->for($fax);

        $this->assertArrayHasKey(self::CACHE_KEY, $this->redisWrites);

        $again = app(RingCentralFaxAttempts::class)->for($fax);

        $this->assertCount(1, $this->ringCentralRequests, 'The second look must come from the cache.');
        $this->assertCount(3, $again['attempts']);
    }

    public function test_a_failed_lookup_is_not_cached(): void
    {
        $this->queueRingCentralResponse(500);
        $fax = $this->failedFax();

        $details = app(RingCentralFaxAttempts::class)->for($fax);

        $this->assertStringContainsString('HTTP 500', (string) $details['error']);
        $this->assertArrayNotHasKey(self::CACHE_KEY, $this->redisWrites);
    }

    public function test_no_lookup_is_made_while_the_extension_is_held_for_its_rate_limit(): void
    {
        app(RingCentralRateLimitState::class)->record('primary', new RingCentralRateLimit(status: 200, group: 'heavy', limit: 10, remaining: 0, window: 60));

        $details = app(RingCentralFaxAttempts::class)->for($this->failedFax());

        $this->assertStringContainsString('rate limit', (string) $details['error']);
        $this->assertCount(0, $this->ringCentralRequests);
    }

    public function test_a_lookup_in_the_sending_group_counts_against_the_extension(): void
    {
        $state = app(RingCentralRateLimitState::class);
        $state->record('primary', new RingCentralRateLimit(status: 200, group: 'heavy', limit: 10, remaining: 5, window: 60));
        $this->queueCallLog($this->rateLimitHeaders(remaining: 0));

        app(RingCentralFaxAttempts::class)->for($this->failedFax());

        // Sends now wait: the lookup spent the last request in the allowance they share.
        $this->assertNotNull($state->availableAt('primary'));
    }

    public function test_a_lookup_in_another_group_leaves_the_sending_numbers_alone(): void
    {
        $state = app(RingCentralRateLimitState::class);
        $state->record('primary', new RingCentralRateLimit(status: 200, group: 'heavy', limit: 10, remaining: 5, window: 60));
        $this->queueCallLog(['X-Rate-Limit-Group' => 'medium', 'X-Rate-Limit-Limit' => '40', 'X-Rate-Limit-Remaining' => '0', 'X-Rate-Limit-Window' => '60']);

        app(RingCentralFaxAttempts::class)->for($this->failedFax());

        $this->assertSame(5, $state->snapshot('primary')['remaining']);
        $this->assertNull($state->availableAt('primary'));
    }

    public function test_it_asks_as_the_extension_that_sent_the_fax(): void
    {
        $sender = RingCentralSender::create(['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'extension_id' => '102']);
        $this->queueCallLog();

        app(RingCentralFaxAttempts::class)->for($this->failedFax(['ringcentral_sender' => $sender->senderKey()]));

        $this->assertSame('access-102', preg_replace('/^bearer\s+/i', '', $this->ringCentralRequests[0]['request']->getHeaderLine('Authorization')));
    }

    public function test_a_fax_ringcentral_never_accepted_has_no_calls_to_show(): void
    {
        $details = app(RingCentralFaxAttempts::class)->for($this->failedFax(['api_fax_id' => null, 'failure_stage' => 'submission']));

        $this->assertStringContainsString('never accepted', (string) $details['error']);
        $this->assertCount(0, $this->ringCentralRequests);
    }

    public function test_the_reading_of_the_attempts(): void
    {
        $attempt = fn (int $duration, string $result) => ['started_at' => '', 'duration' => $duration, 'result' => $result, 'reason' => null];

        $this->assertStringContainsString('busy', (string) RingCentralFaxAttempts::hint([$attempt(4, 'Busy')]));
        $this->assertStringContainsString('Nothing answered', (string) RingCentralFaxAttempts::hint([$attempt(40, 'No Answer')]));
        $this->assertStringContainsString('within a few seconds', (string) RingCentralFaxAttempts::hint([$attempt(3, 'Fax Not Sent'), $attempt(5, 'Fax Not Sent')]));
        $this->assertStringContainsString('The call was answered', (string) RingCentralFaxAttempts::hint([$attempt(64, 'Fax Not Sent')]));
        // A later attempt that got through means there is nothing to explain.
        $this->assertNull(RingCentralFaxAttempts::hint([$attempt(64, 'Fax Not Sent'), $attempt(40, 'Sent')]));
        // Mixed durations: no pattern worth claiming.
        $this->assertNull(RingCentralFaxAttempts::hint([$attempt(5, 'Fax Not Sent'), $attempt(64, 'Fax Not Sent')]));
        $this->assertNull(RingCentralFaxAttempts::hint([]));
    }

    public function test_details_opens_from_the_failed_faxes_list(): void
    {
        $this->actingAs(User::factory()->create());
        $fax = $this->failedFax();
        $this->queueCallLog();

        Livewire::test(CloudFaxingRingCentral::class)
            ->assertSee('Details')
            // The link shows it is working until the modal arrives: the call-log lookup
            // can take several seconds on a first open.
            ->assertSeeHtml('$wire.mountAction(')
            ->assertSeeHtml('x-bind:disabled="loading"')
            ->mountAction('faxAttempts', ['fax' => $fax->id])
            ->assertActionMounted('faxAttempts')
            ->assertMountedActionModalSee([
                'Delivery attempts for IS6707.fs',
                'All 3 calls were answered',
                '64s',
                'SendingFailed (CallFailed)',
            ]);
    }
}
