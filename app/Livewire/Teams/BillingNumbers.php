<?php

namespace App\Livewire\Teams;

use App\Models\Team;
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
        $this->team->allowed_billing = $this->state['allowed_billing'] ?? null;
        $this->team->save();
        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('teams.billing-numbers');
    }
}
