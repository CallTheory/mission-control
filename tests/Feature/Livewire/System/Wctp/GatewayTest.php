<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Livewire\System\Wctp\Gateway;
use App\Models\DataSource;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The gateway page: the endpoint clients post to, and the test-send panel.
 */
class GatewayTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');
        $this->team = $this->createSeededTeam();

        $this->actingAs($this->createUserWithRole($this->team, 'admin'));
    }

    private function configureBandwidth(): void
    {
        DataSource::create([
            'sms_default_provider' => 'bandwidth',
            'bandwidth_account_id' => '5000000',
            'bandwidth_application_id' => 'app-123',
            'bandwidth_from_number' => '+15552220000',
            'bandwidth_api_token' => 'api-token',
            'bandwidth_api_secret' => 'api-secret',
        ]);
    }

    public function test_a_user_without_the_manage_capability_is_denied(): void
    {
        $this->actingAs($this->createUserWithout($this->team, 'technical', Capability::WctpManage));

        Livewire::test(Gateway::class)->assertForbidden();
    }

    public function test_it_shows_the_endpoint_and_asks_for_a_carrier_when_none_is_configured(): void
    {
        Livewire::test(Gateway::class)
            ->assertSet('wctpEndpoint', url('/wctp'))
            ->assertSee('Carrier Configuration Required');
    }

    public function test_the_test_panel_appears_once_a_carrier_can_send(): void
    {
        $this->configureBandwidth();

        Livewire::test(Gateway::class)
            ->assertSee('Carrier Connected Successfully')
            ->assertSet('testProvider', 'bandwidth')
            ->call('toggleTestPanel')
            ->assertSet('showTestPanel', true);
    }

    public function test_a_test_message_goes_out_through_the_chosen_carrier(): void
    {
        $this->configureBandwidth();

        Http::fake(['messaging.bandwidth.com/*' => Http::response(['id' => 'bw-test-1'], 202)]);

        Livewire::test(Gateway::class)
            ->set('testProvider', 'bandwidth')
            ->set('testRecipient', '5551234567')
            ->set('testMessage', 'Hello from the test panel')
            ->call('sendTestMessage')
            ->assertHasNoErrors();

        Http::assertSentCount(1);
    }

    public function test_the_test_panel_validates_its_input(): void
    {
        $this->configureBandwidth();

        Livewire::test(Gateway::class)
            ->set('testRecipient', 'not-a-number')
            ->set('testMessage', '')
            ->call('sendTestMessage')
            ->assertHasErrors(['testRecipient', 'testMessage']);
    }

    public function test_a_carrier_that_cannot_send_is_not_offered(): void
    {
        $this->configureBandwidth();

        Livewire::test(Gateway::class)
            ->set('testProvider', 'twilio')
            ->set('testRecipient', '5551234567')
            ->set('testMessage', 'Hello')
            ->call('sendTestMessage')
            ->assertHasErrors(['testProvider']);
    }
}
