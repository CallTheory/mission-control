<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

final class UtilitiesControllerTest extends TestCase
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
        ]);

        $this->user->teams()->attach($this->team);
    }

    public function test_it_denies_access_for_personal_team(): void
    {
        $personalTeam = Team::factory()->create([
            'personal_team' => true,
        ]);

        $this->user->teams()->attach($personalTeam);
        $this->user->switchTeam($personalTeam);

        $response = $this->actingAs($this->user)
            ->get(route('utilities'));

        $response->assertStatus(403);
    }

    public function test_it_denies_access_for_unauthorized_roles(): void
    {
        $this->user->switchTeam($this->team);
        $role = Jetstream::$roles['agent'];
        $this->user->currentTeam->users()->updateExistingPivot($this->user->id, [
            'role' => $role->key,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities'));

        $response->assertStatus(403);
    }

    public function test_it_allows_access_for_admin_role(): void
    {
        $this->user->switchTeam($this->team);
        $role = Jetstream::$roles['admin'];
        $this->user->currentTeam->users()->updateExistingPivot($this->user->id, [
            'role' => $role->key,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities'));

        $response->assertStatus(200)
            ->assertViewIs('utilities');
    }

    public function test_it_allows_access_for_manager_role(): void
    {
        $this->user->switchTeam($this->team);
        $role = Jetstream::$roles['manager'];
        $this->user->currentTeam->users()->updateExistingPivot($this->user->id, [
            'role' => $role->key,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities'));

        $response->assertStatus(200)
            ->assertViewIs('utilities');
    }

    public function test_it_allows_access_for_supervisor_role(): void
    {
        $this->user->switchTeam($this->team);
        $role = Jetstream::$roles['supervisor'];
        $this->user->currentTeam->users()->updateExistingPivot($this->user->id, [
            'role' => $role->key,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities'));

        $response->assertStatus(200)
            ->assertViewIs('utilities');
    }

    public function test_it_allows_access_for_dispatcher_role(): void
    {
        $this->user->switchTeam($this->team);
        $role = Jetstream::$roles['dispatcher'];
        $this->user->currentTeam->users()->updateExistingPivot($this->user->id, [
            'role' => $role->key,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities'));

        $response->assertStatus(200)
            ->assertViewIs('utilities');
    }
}
