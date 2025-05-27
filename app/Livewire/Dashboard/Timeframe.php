<?php

namespace App\Livewire\Dashboard;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Timeframe extends Component
{
    public array $state = [];

    public function updateDashboardTimeframe(): void
    {
        $user = Auth::user();
        $user->dashboard_timeframe = $this->state['dashboard_timeframe'];
        $user->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->state['dashboard_timeframe'] = Auth::user()->dashboard_timeframe ?? '';
    }

    public function render(): View
    {
        return view('livewire.dashboard.timeframe');
    }
}
