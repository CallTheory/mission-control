<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Livewire\System\User as UserDetail;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * Deleting a user from the System detail screen.
 *
 * The refusal path used to call PHP's exit() inside the Livewire component: the error
 * was recorded and then the process ended, so the response never reached the browser
 * and the operator saw a failure rather than the reason. Nothing covered it.
 */
class UserDeletionTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    public function test_a_user_who_owns_only_a_personal_team_can_be_deleted(): void
    {
        $admin = $this->createUserWithRole($this->createSeededTeam(), 'admin');
        $target = User::factory()->withPersonalTeam()->create();

        Livewire::actingAs($admin)
            ->test(UserDetail::class, ['user' => $target])
            ->callAction('deleteUser');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_a_user_who_owns_a_real_team_is_refused_with_a_reason(): void
    {
        $admin = $this->createUserWithRole($this->createSeededTeam(), 'admin');

        $target = User::factory()->create();
        Team::factory()->create(['user_id' => $target->id, 'personal_team' => false]);

        Livewire::actingAs($admin)
            ->test(UserDetail::class, ['user' => $target])
            ->callAction('deleteUser')
            ->assertNotified();

        // The refusal must leave the account intact, and must actually respond.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
