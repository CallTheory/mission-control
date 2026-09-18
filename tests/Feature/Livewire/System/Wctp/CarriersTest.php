<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Enums\SmsProvider;
use App\Livewire\System\Wctp\Carriers;
use App\Models\DataSource;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The carriers page: which carriers can send, the URLs to paste into their portals,
 * and the carrier a number with none assigned falls back to.
 */
class CarriersTest extends TestCase
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

    public function test_a_user_without_the_manage_capability_is_denied(): void
    {
        $this->actingAs($this->createUserWithout($this->team, 'technical', Capability::WctpManage));

        Livewire::test(Carriers::class)->assertForbidden();
    }

    public function test_it_reports_which_carriers_can_send(): void
    {
        DataSource::create([
            'bandwidth_account_id' => '5000000',
            'bandwidth_application_id' => 'app-123',
            'bandwidth_from_number' => '+15552220000',
            'bandwidth_api_token' => 'api-token',
            'bandwidth_api_secret' => 'api-secret',
        ]);

        Livewire::test(Carriers::class)
            ->assertSee('Bandwidth')
            ->assertSee('Com.io')
            ->assertSee('Ready to send')
            ->assertSee('Not configured')
            // The webhook URLs an operator has to paste into each portal.
            ->assertSee(url('/wctp/sms/bandwidth/incoming'))
            ->assertSee(url('/wctp/sms/commio/incoming'));
    }

    public function test_the_default_carrier_can_be_changed(): void
    {
        Livewire::test(Carriers::class)
            ->callAction('defaultProvider', ['sms_default_provider' => SmsProvider::Commio->value])
            ->assertHasNoErrors();

        $this->assertSame(SmsProvider::Commio->value, DataSource::first()->sms_default_provider);
    }

    public function test_the_dialog_opens_on_the_carrier_currently_in_use(): void
    {
        DataSource::create(['sms_default_provider' => SmsProvider::Bandwidth->value]);

        Livewire::test(Carriers::class)
            ->mountAction('defaultProvider')
            ->assertActionDataSet(['sms_default_provider' => SmsProvider::Bandwidth->value]);
    }
}
