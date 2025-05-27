<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class OpenPanelButton extends Component
{
    public $r;

    public function render(): View
    {
        return view('livewire.open-panel-button');
    }
}
