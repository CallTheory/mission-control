<?php

namespace App\Livewire\Profile;

use App\Enums\DashboardTimeframe;
use App\Enums\ThemePreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

class UserTheme extends Component
{
    public array $state = [];

    public function updateUserTheme(): void
    {
        $this->validate([
            'state.user_theme' => ['required', Rule::enum(ThemePreference::class)],
            'state.dashboard_timeframe' => ['required', Rule::enum(DashboardTimeframe::class)],
        ]);

        $user = Auth::user();
        $user->dark_mode = $this->state['user_theme'];
        $user->show_particles = $this->state['particles'];
        $user->dashboard_timeframe = $this->state['dashboard_timeframe'];
        $user->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->state['user_theme'] = ThemePreference::fromStored(Auth::user()->dark_mode)->value;
        $this->state['particles'] = Auth::user()->show_particles ?? '';
        $this->state['dashboard_timeframe'] = DashboardTimeframe::fromStored(Auth::user()->dashboard_timeframe)->value;
    }

    public function render(): View
    {
        return view('livewire.profile.user-theme');
    }
}
