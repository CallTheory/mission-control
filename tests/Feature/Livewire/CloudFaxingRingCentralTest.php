<?php

namespace Tests\Feature\Livewire;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Livewire\Utilities\CloudFaxingRingCentral;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_it_renders_from_the_cached_snapshot(): void
    {
        $snapshot = [
            'files_to_send' => ['IS20.fs', 'IS20.cap'],
            'files_in_sent' => ['IS19.fs'],
            'files_in_fail' => [],
            'files_in_pre' => [],
            'files_to_send_count' => 2,
            'files_in_sent_count' => 1,
            'files_in_fail_count' => 0,
            'files_in_pre_count' => 0,
            'failed_faxes' => [
                ['id' => '111', 'messageStatus' => 'SendingFailed', 'faxPageCount' => 1, 'to' => [['phoneNumber' => '+15551234567']]],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        Redis::shouldReceive('get')
            ->with(BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY)
            ->andReturn(json_encode($snapshot));

        Livewire::test(CloudFaxingRingCentral::class)
            ->assertSet('state.files_to_send_count', 2)
            ->assertSet('state.files_in_sent_count', 1)
            ->assertSee('+15551234567')
            ->assertSee('SendingFailed');
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
