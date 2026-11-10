<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\RingCentralSender;
use App\Services\Faxing\RingCentralExtension;
use App\Services\Faxing\RingCentralSenderPool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\FakesRingCentral;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Everything that reads a sent fax back from RingCentral has to do it as the extension
 * that sent it: each extension has its own message store.
 */
class RingCentralSenderPoolConsumersTest extends TestCase
{
    use FakesRingCentral;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private RingCentralSender $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('cloud-faxing');

        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_client_secret' => 'client-secret',
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => 'primary-jwt',
            'fax_failure_notification_email' => 'ops@example.test',
        ]);

        $this->second = RingCentralSender::create(['label' => 'Fax 2', 'jwt_token' => 'second-jwt', 'extension_id' => '102']);

        Queue::fake();

        $this->fakeRingCentral(['primary-jwt' => '101', 'second-jwt' => '102']);
    }

    /**
     * @return array<string, string> request path => access token it carried
     */
    private function requestsByPath(): array
    {
        $byPath = [];

        foreach ($this->ringCentralRequests as $entry) {
            $byPath[$entry['request']->getUri()->getPath()] = preg_replace('/^bearer\s+/i', '', $entry['request']->getHeaderLine('Authorization'));
        }

        return $byPath;
    }

    private function pendingFax(string $apiId, ?string $sender): PendingFax
    {
        return PendingFax::create([
            'api_fax_id' => $apiId,
            'fax_provider' => 'ringcentral',
            'ringcentral_sender' => $sender,
            'job_id' => 1131,
            'fs_file_name' => "IS{$apiId}.fs",
            'cap_file' => "IS{$apiId}.cap",
            'filename' => "IS{$apiId}.cap",
            'phone' => '3048222521',
            'original_status' => '2',
            'delivery_status' => 'pending',
            'submitted_at' => now()->subMinutes(10),
        ]);
    }

    public function test_the_pool_is_the_primary_plus_enabled_senders(): void
    {
        $pool = app(RingCentralSenderPool::class);
        $datasource = DataSource::first();

        $this->assertSame(['primary', $this->second->senderKey()], array_map(fn ($e) => $e->key, $pool->extensions($datasource)));

        $this->second->update(['enabled' => false]);

        $this->assertSame(['primary'], array_map(fn ($e) => $e->key, $pool->extensions($datasource)));
        // Still findable: faxes it already sent have to be polled as it.
        $this->assertSame('second-jwt', $pool->find($datasource, $this->second->senderKey())?->jwt);
        $this->assertSame(RingCentralExtension::PRIMARY, $pool->find($datasource, null)?->key);
    }

    public function test_the_poller_checks_each_fax_as_the_extension_that_sent_it(): void
    {
        $this->pendingFax('5001', null);
        $this->pendingFax('5002', $this->second->senderKey());

        $this->queueRingCentralResponse(200, [], ['id' => '5001', 'messageStatus' => 'Delivered']);
        $this->queueRingCentralResponse(200, [], ['id' => '5002', 'messageStatus' => 'Delivered']);

        $this->artisan('isfax:check-pending')->assertSuccessful();

        $requests = $this->requestsByPath();

        $this->assertSame('access-101', $requests['/restapi/v1.0/account/~/extension/~/message-store/5001'] ?? null);
        $this->assertSame('access-102', $requests['/restapi/v1.0/account/~/extension/~/message-store/5002'] ?? null);
        $this->assertSame(0, PendingFax::where('delivery_status', 'pending')->count());
    }

    public function test_a_throttled_extension_does_not_stop_polling_the_others(): void
    {
        $this->pendingFax('5001', null);
        $this->pendingFax('5002', $this->second->senderKey());

        $this->queueRingCentralResponse(429, $this->rateLimitHeaders(remaining: 0, retryAfter: 60));
        $this->queueRingCentralResponse(200, [], ['id' => '5002', 'messageStatus' => 'Delivered']);

        $this->artisan('isfax:check-pending')->assertSuccessful();

        $this->assertSame('pending', PendingFax::where('api_fax_id', '5001')->value('delivery_status'));
        $this->assertSame('success', PendingFax::where('api_fax_id', '5002')->value('delivery_status'));
    }

    public function test_the_dashboard_lists_every_extensions_faxes_tagged_with_their_sender(): void
    {
        $this->queueRingCentralResponse(200, [], ['records' => [['id' => '5001', 'creationTime' => '2026-11-09T14:01:00Z']]]);
        $this->queueRingCentralResponse(200, [], ['records' => [['id' => '5002', 'creationTime' => '2026-11-09T14:02:00Z']]]);

        $this->artisan('isfax:build-ringcentral-dashboard')->assertSuccessful();

        $snapshot = json_decode($this->redisWrites[BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY], true);
        $faxes = $snapshot['failed_faxes'];

        // Newest first, across both message stores.
        $this->assertSame(['5002', '5001'], array_column($faxes, 'id'));
        $this->assertSame([$this->second->senderKey(), 'primary'], array_column($faxes, 'mc_sender'));
    }

    public function test_one_extension_failing_keeps_its_previous_records_and_shows_the_rest(): void
    {
        $this->redisWrites[BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY] = json_encode(['failed_faxes' => [
            ['id' => '4001', 'creationTime' => '2026-11-08T10:00:00Z', 'mc_sender' => 'primary'],
            ['id' => '4002', 'creationTime' => '2026-11-08T10:00:00Z', 'mc_sender' => $this->second->senderKey()],
        ]]);

        $this->queueRingCentralResponse(503);
        $this->queueRingCentralResponse(200, [], ['records' => [['id' => '5002', 'creationTime' => '2026-11-09T14:02:00Z']]]);

        $this->artisan('isfax:build-ringcentral-dashboard')->assertSuccessful();

        $faxes = json_decode($this->redisWrites[BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY], true)['failed_faxes'];

        $this->assertSame(['5002', '4001'], array_column($faxes, 'id'));
    }
}
