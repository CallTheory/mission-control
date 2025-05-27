<?php

namespace App\Livewire\System\Integrations;

use Illuminate\View\View;
use Livewire\Component;

class Twilio extends Component
{
    public $isOpen = false;

    public function render(): View
    {
        return view('livewire.system.integrations.twilio');
    }
}
