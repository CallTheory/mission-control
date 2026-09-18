<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Livewire\Teams\EnabledUtilities;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Team Utilities gates each toggle on its own system feature flag, so with no
 * flags set the section used to render as an empty form with no explanation.
 */
class EnabledUtilitiesEmptyStateTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private function team(): Team
    {
        return $this->createSeededTeam();
    }

    public function test_no_system_utilities_means_the_notice_is_shown(): void
    {
        $team = $this->team();
        $admin = $this->createUserWithRole($team, 'admin');

        Livewire::actingAs($admin)
            ->test(EnabledUtilities::class, ['team' => $team])
            ->assertSet('api_gateway', false)
            ->assertSee('No utilities are enabled at the system level.')
            ->assertSee('A utility has to be turned on for the whole system before any team can enable it here.')
            ->assertDontSee('API Gateway');
    }

    public function test_an_admin_is_offered_a_link_to_system_settings(): void
    {
        $team = $this->team();
        $admin = $this->createUserWithRole($team, 'admin');

        Livewire::actingAs($admin)
            ->test(EnabledUtilities::class, ['team' => $team])
            ->assertSee('Enable utilities in System settings')
            ->assertSee(route('system'), escape: false);
    }

    public function test_a_user_without_system_access_is_told_to_ask_an_administrator(): void
    {
        $team = $this->team();
        $agent = $this->createUserWithRole($team, 'agent');

        Livewire::actingAs($agent)
            ->test(EnabledUtilities::class, ['team' => $team])
            ->assertSee('Ask an administrator to enable one in System settings.')
            ->assertDontSee('Enable utilities in System settings');
    }

    public function test_one_enabled_system_utility_replaces_the_notice_with_the_toggles(): void
    {
        $this->enableSystemFeature('api-gateway');

        $team = $this->team();
        $admin = $this->createUserWithRole($team, 'admin');

        Livewire::actingAs($admin)
            ->test(EnabledUtilities::class, ['team' => $team])
            ->assertDontSee('No utilities are enabled at the system level.')
            ->assertSee('API Gateway')
            ->assertDontSee('Board Check');
    }

    public function test_the_helper_reflects_every_utility_in_the_enum(): void
    {
        $team = $this->team();
        $admin = $this->createUserWithRole($team, 'admin');

        $component = Livewire::actingAs($admin)
            ->test(EnabledUtilities::class, ['team' => $team]);

        $this->assertFalse($component->instance()->hasSystemEnabledUtilities());

        // A utility nowhere near the top of the enum still counts.
        $this->enableSystemFeature('script-search');

        $this->assertTrue($component->instance()->hasSystemEnabledUtilities());
    }

    public function test_saving_a_toggle_no_longer_depends_on_a_dev_only_class(): void
    {
        $this->enableSystemFeature('api-gateway');

        $team = $this->team();
        $admin = $this->createUserWithRole($team, 'admin');

        Livewire::actingAs($admin)
            ->test(EnabledUtilities::class, ['team' => $team])
            ->call('toggleSetting', 'api_gateway')
            ->assertHasNoErrors()
            ->assertDispatched('saved');

        $this->assertTrue((bool) $team->fresh()->utility_api_gateway);
    }

    public function test_an_unknown_setting_is_rejected(): void
    {
        $team = $this->team();
        $admin = $this->createUserWithRole($team, 'admin');

        Livewire::actingAs($admin)
            ->test(EnabledUtilities::class, ['team' => $team])
            ->call('toggleSetting', 'not_a_utility')
            ->assertStatus(400);
    }
}
