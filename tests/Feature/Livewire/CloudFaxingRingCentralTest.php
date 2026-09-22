<?php

namespace Tests\Feature\Livewire;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Livewire\Utilities\CloudFaxingRingCentral;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\User;
use App\Services\Faxing\FaxDashboardSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\TestCase;

class CloudFaxingRingCentralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        DataSource::create([
            'ringcentral_client_id' => 'test-client-id',
            'ringcentral_client_secret' => encrypt('secret'),
            'ringcentral_jwt_token' => encrypt('jwt'),
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
        ]);

        $this->actingAs(User::factory()->create());
    }

    public function test_it_renders_from_the_cached_snapshots(): void
    {
        // The snapshot is split in two. The spool folders are per source, because several
        // Intelligent Series servers have separate directories; the RingCentral fax list
        // and webhook heartbeat are account-level and shared, because a provider callback
        // carries no notion of which server produced the fax.
        Cache::put(FaxDashboardSnapshot::key('ringcentral'), [
            'files_to_send' => [],
            'files_in_sent' => [],
            'files_in_fail' => [],
            'files_in_pre' => [],
            'files_to_send_count' => 2,
            'files_in_sent_count' => 1,
            'files_in_fail_count' => 0,
            'files_in_pre_count' => 0,
            'generated_at' => now()->toIso8601String(),
        ], 180);

        Redis::shouldReceive('get')
            ->with(BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY)
            ->andReturn(json_encode([
                'failed_faxes' => [
                    ['id' => '111', 'messageStatus' => 'SendingFailed', 'faxPageCount' => 1, 'to' => [['phoneNumber' => '+15551234567']]],
                ],
                'generated_at' => now()->toIso8601String(),
            ]));

        Livewire::test(CloudFaxingRingCentral::class)
            ->assertSet('state.files_to_send_count', 2)
            ->assertSet('state.files_in_sent_count', 1)
            ->assertSee('+15551234567')
            ->assertSee('SendingFailed');
    }

    public function test_each_source_renders_its_own_spool(): void
    {
        FaxSpoolSource::create(['key' => 'is2', 'name' => 'IS 2']);

        Cache::put(FaxDashboardSnapshot::key('ringcentral'), ['files_to_send_count' => 2], 180);
        Cache::put(FaxDashboardSnapshot::key('is2'), ['files_to_send_count' => 7], 180);

        Redis::shouldReceive('get')->andReturn(null);

        Livewire::test(CloudFaxingRingCentral::class, ['source' => 'is2'])
            ->assertSet('sourceKey', 'is2')
            ->assertSet('state.files_to_send_count', 7);
    }

    public function test_an_unknown_source_falls_back_rather_than_erroring(): void
    {
        // These keys arrive from bookmarks and from alert emails that outlive the source
        // they named.
        Redis::shouldReceive('get')->andReturn(null);

        Livewire::test(CloudFaxingRingCentral::class, ['source' => 'nope'])
            ->assertSet('sourceKey', 'ringcentral');
    }

    public function test_it_keeps_default_state_when_no_snapshot_exists(): void
    {
        Redis::shouldReceive('get')
            ->with(BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY)
            ->andReturn(null);

        Livewire::test(CloudFaxingRingCentral::class)
            ->assertSet('state.files_to_send_count', 0)
            ->assertSet('state.ringcentral_failed_faxes', []);
    }
}
