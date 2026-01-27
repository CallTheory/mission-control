<?php

declare(strict_types=1);

namespace Tests\Feature\VoicemailDigest;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class VoicemailDigestControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'personal_team' => false,
            'utility_voicemail_digest' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('feature-flags');
        parent::tearDown();
    }

    public function test_it_returns_404_when_system_feature_flag_is_disabled(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest'));

        $response->assertStatus(404);
    }

    public function test_it_returns_404_when_team_utility_is_disabled(): void
    {
        $this->enableSystemFeatureFlag();

        $this->team->utility_voicemail_digest = false;
        $this->team->save();

        // Reload user with fresh currentTeam relation
        $this->user->unsetRelation('currentTeam');
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest'));

        $response->assertStatus(404);
    }

    public function test_it_returns_403_for_personal_team(): void
    {
        $this->enableSystemFeatureFlag();

        $personalTeam = Team::factory()->create([
            'personal_team' => true,
            'utility_voicemail_digest' => true,
        ]);

        $this->user->teams()->attach($personalTeam, ['role' => 'admin']);
        $this->user->switchTeam($personalTeam);
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest'));

        $response->assertStatus(403);
    }

    public function test_it_allows_access_when_feature_and_utility_enabled(): void
    {
        $this->enableSystemFeatureFlag();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest'));

        $response->assertStatus(200)
            ->assertViewIs('utilities.voicemail-digest');
    }

    public function test_it_requires_authentication(): void
    {
        $this->enableSystemFeatureFlag();

        $response = $this->get(route('utilities.voicemail-digest'));

        $response->assertRedirect(route('login'));
    }

    public function test_it_checks_team_utility_flag_for_current_team(): void
    {
        $this->enableSystemFeatureFlag();

        $team2 = Team::factory()->create([
            'personal_team' => false,
            'utility_voicemail_digest' => false,
        ]);

        $this->user->teams()->attach($team2, ['role' => 'admin']);
        $this->user->switchTeam($team2);
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.voicemail-digest'));

        $response->assertStatus(404);
    }

    private function enableSystemFeatureFlag(): void
    {
        Storage::makeDirectory('feature-flags');
        $encrypted = encrypt('voicemail-digest');
        Storage::put('feature-flags/voicemail-digest.flag', $encrypted);
    }
}
