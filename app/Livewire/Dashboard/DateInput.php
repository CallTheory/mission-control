<?php

namespace App\Livewire\Dashboard;

use Illuminate\View\View;
use Livewire\Component;

class DateInput extends Component
{
    public string $input;

    public string $label = 'Date';

    public function render(): View
    {
        return view('livewire.dashboard.date-input');
    }
}
