<?php

namespace Tests\Feature\Permissions;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use App\Enums\Capability;
use App\Models\SuffixRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CapabilityGateTest extends TestCase
{
    use RefreshDatabase;

    private function teamWithRoles(bool $boardCheckEnabled = true): Team
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'personal_team' => false,
            'utility_board_check' => $boardCheckEnabled,
        ]);

        (new SeedDefaultRolesForTeam)($team);

        return $team;
    }

    private function member(Team $team, string $roleKey): User
    {
        $user = User::factory()->create();
        $team->users()->attach($user, ['role' => $roleKey]);
        $user->switchTeam($team);
        $user->assignRole($team->roles()->where('key', $roleKey)->firstOrFail());

        return $user->fresh();
    }

    private function enableFeature(string $flag): void
    {
        Storage::put("feature-flags/{$flag}.flag", encrypt($flag));
    }

    public function test_utility_gate_requires_system_flag(): void
    {
        Storage::fake();
        $team = $this->teamWithRoles();
        $supervisor = $this->member($team, 'supervisor');

        // System flag OFF: even a capable role is denied.
        $this->assertFalse(Gate::forUser($supervisor)->check(Capability::UtilityBoardCheck->value));

        // System flag ON: allowed.
        $this->enableFeature('board-check');
        $this->assertTrue(Gate::forUser($supervisor)->check(Capability::UtilityBoardCheck->value));
    }

    public function test_utility_gate_requires_team_flag(): void
    {
        Storage::fake();
        $this->enableFeature('board-check');

        $team = $this->teamWithRoles(boardCheckEnabled: false);
        $supervisor = $this->member($team, 'supervisor');

        // Team flag OFF -> denied even with system flag + capability.
        $this->assertFalse(Gate::forUser($supervisor)->check(Capability::UtilityBoardCheck->value));

        $team->utility_board_check = true;
        $team->save();
        $this->assertTrue(Gate::forUser($supervisor->fresh())->check(Capability::UtilityBoardCheck->value));
    }

    public function test_role_without_capability_is_denied_even_when_flags_on(): void
    {
        Storage::fake();
        $this->enableFeature('board-check');

        $team = $this->teamWithRoles();
        // 'technical' does not get board_check in the default template.
        $technical = $this->member($team, 'technical');

        $this->assertFalse(Gate::forUser($technical)->check(Capability::UtilityBoardCheck->value));
    }

    public function test_suffix_rule_grants_capability_via_agent_name(): void
    {
        $team = $this->teamWithRoles();

        // Default global -SUP rule is seeded from config on team creation path;
        // seed it here explicitly for the isolated team.
        (new SeedDefaultRolesForTeam)->seedGlobalSuffixRules();

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getIntelligentAgent')
            ->andReturn((object) ['Name' => 'JDOE-SUP']);

        $caps = $user->suffixCapabilities($team);

        $this->assertTrue($caps->contains(Capability::UtilityBoardCheck->value));
        $this->assertTrue($caps->contains(Capability::BoardReview->value));
        $this->assertTrue($caps->contains(Capability::UtilityCloudFaxing->value));
    }

    public function test_suffix_disp_grants_only_board_check(): void
    {
        $team = $this->teamWithRoles();
        (new SeedDefaultRolesForTeam)->seedGlobalSuffixRules();

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getIntelligentAgent')
            ->andReturn((object) ['Name' => 'JDOE-DISP']);

        $caps = $user->suffixCapabilities($team);

        $this->assertTrue($caps->contains(Capability::UtilityBoardCheck->value));
        $this->assertFalse($caps->contains(Capability::BoardReview->value));
    }

    public function test_no_agent_name_grants_no_suffix_capabilities(): void
    {
        $team = $this->teamWithRoles();
        (new SeedDefaultRolesForTeam)->seedGlobalSuffixRules();

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getIntelligentAgent')->andReturn(null);

        $this->assertTrue($user->suffixCapabilities($team)->isEmpty());
    }

    public function test_team_scoped_suffix_rule_only_applies_to_its_team(): void
    {
        $teamA = $this->teamWithRoles();
        $teamB = $this->teamWithRoles();

        SuffixRule::create([
            'team_id' => $teamA->id,
            'match_type' => 'contains',
            'pattern' => '-NIGHT',
            'capability' => Capability::UtilityDatabaseHealth->value,
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getIntelligentAgent')
            ->andReturn((object) ['Name' => 'JDOE-NIGHT']);

        $this->assertTrue($user->suffixCapabilities($teamA)->contains(Capability::UtilityDatabaseHealth->value));
        $this->assertFalse($user->suffixCapabilities($teamB)->contains(Capability::UtilityDatabaseHealth->value));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
