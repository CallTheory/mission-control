<?php

namespace App\Livewire\Navigation;

use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class AccountModal extends ModalComponent
{
    public function render(): View
    {
        return view('livewire.navigation.account-modal');
    }
}
