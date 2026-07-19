<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordingAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userOnTeam(array $teamAttributes = []): User
    {
        $team = Team::factory()->create(array_merge(['personal_team' => false], $teamAttributes));
        $user = User::factory()->create();
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user;
    }

    public function test_unrestricted_team_is_denied_recording_by_default(): void
    {
        // Team with no allow-lists configured; the fail-closed default must 403
        // before the (switch-DB) call lookup is ever attempted.
        config(['recordings.allow_unrestricted_teams' => false]);
        $user = $this->userOnTeam(['allowed_accounts' => null, 'allowed_billing' => null]);

        $this->actingAs($user)
            ->get('/utilities/recording/12345.wav')
            ->assertForbidden();
    }

    public function test_unrestricted_team_is_denied_screencapture_by_default(): void
    {
        config(['recordings.allow_unrestricted_teams' => false]);
        // screencapture feature must be enabled to get past the 404 gate.
        \Illuminate\Support\Facades\Storage::makeDirectory('feature-flags');
        \Illuminate\Support\Facades\Storage::put('feature-flags/screencaptures.flag', encrypt('screencaptures'));

        $user = $this->userOnTeam(['allowed_accounts' => null, 'allowed_billing' => null]);

        $this->actingAs($user)
            ->get('/utilities/screencapture/12345.mp4')
            ->assertForbidden();

        \Illuminate\Support\Facades\Storage::delete('feature-flags/screencaptures.flag');
    }

    public function test_opt_in_config_bypasses_the_unrestricted_guard(): void
    {
        // With the opt-in enabled the guard no longer 403s; the request proceeds to
        // the call lookup instead (which, lacking a switch DB in tests, 400s). The
        // point is simply that the response is NOT 403.
        config(['recordings.allow_unrestricted_teams' => true]);
        $user = $this->userOnTeam(['allowed_accounts' => null, 'allowed_billing' => null]);

        $response = $this->actingAs($user)->get('/utilities/recording/12345.wav');

        $this->assertNotSame(403, $response->getStatusCode());
    }
}
