<?php

namespace App\Livewire\Teams;

use App\Models\Team;
use App\Support\TeamAccountScope;
use Illuminate\View\View;
use Livewire\Component;

class BillingNumbers extends Component
{
    public Team $team;

    public array $state;

    public function mount(Team $team): void
    {
        $this->team = $team;
        $this->state['allowed_billing'] = $this->team->allowed_billing ?? '';
    }

    public function saveAccounts(): void
    {
        $billing = $this->state['allowed_billing'] ?? null;

        // Clearing the last list on a restricted team would leave it unconfigured, so
        // the same invariant applies here as in the Allowed Accounts form.
        TeamAccountScope::validate(
            allowedAccounts: $this->team->allowed_accounts,
            allowedBilling: $billing,
            unrestricted: $this->team->unrestricted_accounts,
            field: 'state.allowed_billing',
        );

        $this->team->allowed_billing = $billing;
        $this->team->save();
        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('teams.billing-numbers');
    }
}
