<?php

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use Illuminate\View\View;
use Livewire\Component;

class Tools extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    public function render(): View
    {
        return view('livewire.system.tools');
    }
}
