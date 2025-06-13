<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(), // Mark the user as verified
        ]);

        // Create a personal team for the user
        $team = Team::forceCreate([
            'user_id' => $this->user->id,
            'name' => explode(' ', $this->user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $this->user->ownedTeams()->save($team);
        $this->user->switchTeam($team);

        Settings::factory()->create(['switch_data_timezone' => 'UTC']);
    }

    public function test_dashboard_page_can_be_rendered(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200)->assertViewIs('dashboard');
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
    }
}
