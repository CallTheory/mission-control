<?php

namespace App\Livewire\Dashboard;

use App\Models\Stats\Calls\RecentCalls as Recent;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class RecentCalls extends Component
{
    public array $recentCalls;

    public function render(): View
    {
        try {
            $recent = new Recent;
        } catch (Exception $e) {
        }
        $this->recentCalls = $recent->results ?? [];

        return view('livewire.dashboard.recent-calls');
    }
}
