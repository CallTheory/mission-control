<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class OpenEmailButton extends Component
{
    public $email;

    public function render(): View
    {
        return view('livewire.open-email-button');
    }
}
