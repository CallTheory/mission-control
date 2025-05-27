<?php

namespace App\Livewire\Teams;

use App\Models\Team;
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
    }

    public function saveAccounts(): void
    {
        $this->team->allowed_accounts = $this->state['allowed_accounts'] ?? null;
        $this->team->save();
        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('teams.accounts');
    }
}
