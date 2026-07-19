<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\System;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Jetstream\Jetstream;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminOnTeam(): array
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $admin = User::factory()->create();
        $admin->teams()->attach($team, ['role' => Jetstream::$roles['admin']->key ?? 'admin']);
        $admin->switchTeam($team);

        return [$admin, $team];
    }

    public function test_admin_can_view_user_in_their_team(): void
    {
        [$admin, $team] = $this->adminOnTeam();

        $member = User::factory()->create();
        $member->teams()->attach($team, ['role' => 'agent']);

        $this->actingAs($admin)
            ->get("/system/users/{$member->id}")
            ->assertOk();
    }

    public function test_admin_cannot_view_user_from_another_team(): void
    {
        [$admin] = $this->adminOnTeam();

        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $outsider = User::factory()->create();
        $outsider->teams()->attach($otherTeam, ['role' => 'agent']);

        $this->actingAs($admin)
            ->get("/system/users/{$outsider->id}")
            ->assertForbidden();
    }
}
