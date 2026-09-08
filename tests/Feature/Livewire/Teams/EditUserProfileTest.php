<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Teams;

use App\Livewire\Teams\EditUserProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The team profile editor after moving from a hand-rolled dialog to a Filament action.
 */
class EditUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_edits_name_email_and_agent_link(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.test']);
        Livewire::actingAs($user)->test(EditUserProfile::class, ['user' => $user])
            ->assertOk()
            ->callAction('editProfile', ['name' => 'New Name', 'email' => 'new@example.test', 'agtId' => null])
            ->assertHasNoErrors();
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertNull($user->agtId);
    }

    public function test_email_must_be_unique_but_may_stay_the_same(): void
    {
        $taken = User::factory()->create(['email' => 'taken@example.test']);
        $user = User::factory()->create(['email' => 'mine@example.test']);
        Livewire::actingAs($user)->test(EditUserProfile::class, ['user' => $user])
            // The previous form validated the address format but not its uniqueness,
            // so a profile edit could quietly take another account's email.
            ->callAction('editProfile', ['name' => 'X', 'email' => 'taken@example.test'])
            ->assertHasFormErrors(['email']);
        Livewire::actingAs($user)->test(EditUserProfile::class, ['user' => $user])
            ->callAction('editProfile', ['name' => 'Same Email OK', 'email' => 'mine@example.test'])
            ->assertHasNoErrors();
    }
}
