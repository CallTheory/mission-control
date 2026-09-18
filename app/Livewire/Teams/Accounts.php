<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use App\Support\TeamAccountScope;
use Illuminate\View\View;
use Livewire\Component;

class Accounts extends Component
{
    public Team $team;

    public array $state;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->state['allowed_accounts'] = $this->team->allowed_accounts ?? '';
        $this->state['unrestricted_accounts'] = $this->team->unrestricted_accounts;
    }

    public function saveAccounts(): void
    {
        $accounts = $this->state['allowed_accounts'] ?? null;
        $unrestricted = (bool) ($this->state['unrestricted_accounts'] ?? false);

        // The billing list is edited by a sibling form, so it is read as-is: the
        // invariant is about the team's resulting scope, not this form's fields.
        TeamAccountScope::validate(
            allowedAccounts: $accounts,
            allowedBilling: $this->team->allowed_billing,
            unrestricted: $unrestricted,
            field: 'state.allowed_accounts',
        );

        $this->team->allowed_accounts = $accounts;
        $this->team->unrestricted_accounts = $unrestricted;
        $this->team->save();
        $this->dispatch('saved');
    }

    /**
     * Ticking "every account" while a list is present would be a contradiction: the
     * list still filters, everywhere. Clearing it makes the two agree.
     */
    public function updatedState($value, string $key): void
    {
        if ($key === 'unrestricted_accounts' && (bool) $value === true) {
            $this->state['allowed_accounts'] = '';
        }
    }

    public function render(): View
    {
        return view('teams.accounts');
    }
}
