<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use App\Livewire\Teams\Accounts;
use App\Livewire\Teams\BillingNumbers;
use App\Models\Team;
use App\Models\User;
use App\Support\TeamAccountScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A shared team must either list the accounts it may see or be marked as seeing every
 * account. "Neither" is the ambiguous state -- indistinguishable from a team nobody
 * configured -- that CallAccess withholds call data from, so the settings forms refuse
 * to create it.
 */
class AccountScopeInvariantTest extends TestCase
{
    use RefreshDatabase;

    private function owner(Team $team): User
    {
        $user = User::factory()->create();
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);
        $team->forceFill(['user_id' => $user->id])->save();

        return $user;
    }

    private function sharedTeam(array $attributes = []): Team
    {
        return Team::factory()->create(array_merge([
            'personal_team' => false,
            'allowed_accounts' => '1000-2000',
            'allowed_billing' => null,
            'unrestricted_accounts' => false,
        ], $attributes));
    }

    public function test_clearing_the_last_list_without_ticking_the_box_is_rejected(): void
    {
        $team = $this->sharedTeam();

        Livewire::actingAs($this->owner($team))
            ->test(Accounts::class, ['team' => $team])
            ->set('state.allowed_accounts', '')
            ->call('saveAccounts')
            ->assertHasErrors('state.allowed_accounts');

        $this->assertSame('1000-2000', $team->fresh()->allowed_accounts);
    }

    public function test_clearing_the_last_list_is_allowed_once_the_box_is_ticked(): void
    {
        $team = $this->sharedTeam();

        Livewire::actingAs($this->owner($team))
            ->test(Accounts::class, ['team' => $team])
            ->set('state.unrestricted_accounts', true)
            ->call('saveAccounts')
            ->assertHasNoErrors();

        $team->refresh();

        $this->assertTrue($team->unrestricted_accounts);
        // Ticking the box clears the list: a list would still filter, everywhere, which
        // would contradict what the box says.
        $this->assertSame('', $team->allowed_accounts);
    }

    public function test_a_billing_list_alone_satisfies_the_invariant(): void
    {
        $team = $this->sharedTeam(['allowed_accounts' => null, 'allowed_billing' => '500']);

        Livewire::actingAs($this->owner($team))
            ->test(Accounts::class, ['team' => $team])
            ->set('state.allowed_accounts', '')
            ->call('saveAccounts')
            ->assertHasNoErrors();
    }

    public function test_the_billing_form_cannot_clear_the_last_list_either(): void
    {
        $team = $this->sharedTeam(['allowed_accounts' => null, 'allowed_billing' => '500']);

        Livewire::actingAs($this->owner($team))
            ->test(BillingNumbers::class, ['team' => $team])
            ->set('state.allowed_billing', '')
            ->call('saveAccounts')
            ->assertHasErrors('state.allowed_billing');

        $this->assertSame('500', $team->fresh()->allowed_billing);
    }

    /**
     * A list of whitespace reads as configured but filters nothing, so it must not
     * satisfy the invariant -- otherwise it is the ambiguous state wearing a disguise.
     */
    public function test_whitespace_does_not_count_as_a_list(): void
    {
        $this->assertFalse(TeamAccountScope::isDecided("  \n ", null, false));
        $this->assertTrue(TeamAccountScope::isDecided("  \n ", null, true));
        $this->assertTrue(TeamAccountScope::isDecided('1000', null, false));
        $this->assertTrue(TeamAccountScope::isDecided(null, '500', false));
        $this->assertFalse(TeamAccountScope::isDecided(null, null, false));
    }

    public function test_personal_teams_need_no_decision(): void
    {
        $personal = Team::factory()->create([
            'personal_team' => true,
            'allowed_accounts' => null,
            'allowed_billing' => null,
            'unrestricted_accounts' => false,
        ]);

        $this->assertTrue($personal->hasDecidedAccountScope());
    }
}
