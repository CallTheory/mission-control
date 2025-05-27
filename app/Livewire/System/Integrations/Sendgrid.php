<?php

namespace App\Livewire\System\Integrations;

use Illuminate\View\View;
use Livewire\Component;

class Sendgrid extends Component
{
    public bool $isOpen = false;

    public function render(): View
    {
        return view('livewire.system.integrations.sendgrid');
    }
}
