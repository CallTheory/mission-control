<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class UserTheme extends Component
{
    public array $state = [];

    public function updateUserTheme(): void
    {
        $user = Auth::user();
        $user->dark_mode = $this->state['user_theme'];
        $user->show_particles = $this->state['particles'];
        $user->dashboard_timeframe = $this->state['dashboard_timeframe'];
        $user->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->state['user_theme'] = Auth::user()->dark_mode ?? '';
        $this->state['particles'] = Auth::user()->show_particles ?? '';
        $this->state['dashboard_timeframe'] = Auth::user()->dashboard_timeframe ?? '';
    }

    public function render(): View
    {
        return view('livewire.profile.user-theme');
    }
}
