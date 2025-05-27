<?php

namespace App\Livewire\Navigation;

use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class TimeModal extends ModalComponent
{
    public function render(): View
    {
        return view('livewire.navigation.time-modal');
    }
}
