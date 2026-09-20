<?php

namespace App\Livewire\Dashboard;

use App\Enums\DashboardTimeframe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class Timeframe extends Component
{
    public array $state = [];

    public function updateDashboardTimeframe(): void
    {
        $this->validate([
            'state.dashboard_timeframe' => ['required', Rule::enum(DashboardTimeframe::class)],
        ]);

        $user = Auth::user();
        $user->dashboard_timeframe = $this->state['dashboard_timeframe'];
        $user->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        // Normalised so the <select> matches the stored preference even on the
        // legacy '' rows, which no longer have an option of their own.
        $this->state['dashboard_timeframe'] = DashboardTimeframe::fromStored(Auth::user()->dashboard_timeframe)->value;
    }

    public function render(): View
    {
        return view('livewire.dashboard.timeframe');
    }
}
