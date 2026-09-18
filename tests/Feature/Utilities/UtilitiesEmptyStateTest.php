<?php

declare(strict_types=1);

namespace Tests\Feature\Utilities;

use App\Enums\Capability;
use App\Models\Team;
use App\Models\User;
use App\Support\UtilityAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The Utilities index gates every tile, so an empty grid used to leave only the
 * "Utilities are augmented and/or additional features" blurb and nothing else.
 * Which of the four gate conditions failed decides what the page should say.
 */
class UtilitiesEmptyStateTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private function enableForTeam(Team $team, string $column): void
    {
        $team->forceFill([$column => true])->save();
    }

    public function test_no_system_utilities_says_so_and_points_an_admin_at_system(): void
    {
        $team = $this->createSeededTeam();
        $admin = $this->createUserWithRole($team, 'admin');

        $response = $this->actingAs($admin)->get('/utilities');

        $response->assertOk();
        $response->assertSee('No utilities are enabled at the system level.');
        $response->assertSee('Enable utilities in System settings');
        $response->assertSee(route('system'), escape: false);
    }

    public function test_a_non_admin_is_told_who_to_ask_instead_of_a_dead_link(): void
    {
        $team = $this->createSeededTeam();
        // Manager can reach the page (utilities.access) but cannot fix this
        // (no system.access), which is exactly the case that needs the
        // "ask someone" wording rather than a link they would get a 403 from.
        $manager = $this->createUserWithRole($team, 'manager');

        $response = $this->actingAs($manager)->get('/utilities');

        $response->assertOk();
        $response->assertSee('Ask an administrator to sort this out for you.');
        $response->assertDontSee(route('system'), escape: false);
    }

    public function test_system_enabled_but_team_has_none_points_at_team_settings(): void
    {
        $this->enableSystemFeature('board-check');

        $team = $this->createSeededTeam();
        $admin = $this->createUserWithRole($team, 'admin');

        $response = $this->actingAs($admin)->get('/utilities');

        $response->assertOk();
        $response->assertSee('This team has not enabled any utilities yet.');
        $response->assertSee('Choose utilities in Team Settings');
    }

    public function test_team_enabled_but_role_lacks_the_capability_points_at_permissions(): void
    {
        $this->enableSystemFeature('board-check');

        $team = $this->createSeededTeam();
        $this->enableForTeam($team, 'utility_board_check');

        // An admin who has had the board check capability taken away: the team
        // has the utility on, the role simply does not grant it.
        $admin = $this->createUserWithout($team, 'admin', Capability::UtilityBoardCheck);

        $response = $this->actingAs($admin)->get('/utilities');

        $response->assertOk();
        $response->assertSee('does not include access to any of this team', escape: false);
        $response->assertSee('Review roles and permissions');
    }

    public function test_an_available_utility_shows_the_grid_and_no_notice(): void
    {
        $this->enableSystemFeature('board-check');

        $team = $this->createSeededTeam();
        $this->enableForTeam($team, 'utility_board_check');
        $admin = $this->createUserWithRole($team, 'admin');

        $response = $this->actingAs($admin)->get('/utilities');

        $response->assertOk();
        $response->assertSee('Board Check');
        $response->assertDontSee('No utilities are enabled at the system level.');
        $response->assertDontSee('This team has not enabled any utilities yet.');
    }

    public function test_cloud_faxing_without_a_provider_does_not_count_as_available(): void
    {
        // Cloud Faxing is gated a second time on a provider being configured, so
        // a team whose only utility is Cloud Faxing still has an empty grid.
        $this->enableSystemFeature('cloud-faxing');

        $team = $this->createSeededTeam();
        $this->enableForTeam($team, 'utility_cloud_faxing');
        $admin = $this->createUserWithRole($team, 'admin');

        $availability = new UtilityAvailability($admin);

        $this->assertFalse($availability->hasAny());

        // Not the user's role: they hold the capability, the provider is the
        // thing that is missing, so the page must not send them to permissions.
        $this->assertSame(UtilityAvailability::REASON_MISSING_DEPENDENCY, $availability->reason());

        $response = $this->actingAs($admin)->get('/utilities');

        $response->assertOk();
        $response->assertSee('not finished being set up', escape: false);
        $response->assertDontSee('Review roles and permissions');
    }

    public function test_a_personal_team_is_told_to_switch_teams(): void
    {
        $user = User::factory()->create();
        $personal = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->switchTeam($personal);

        $availability = new UtilityAvailability($user->fresh());

        $this->assertSame(UtilityAvailability::REASON_PERSONAL_TEAM, $availability->reason());
        $this->assertNull($availability->fixRoute());
    }
}
