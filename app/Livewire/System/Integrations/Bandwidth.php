<?php

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use Illuminate\View\View;
use Livewire\Component;

class Bandwidth extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    public $isOpen = false;

    public function render(): View
    {
        return view('livewire.system.integrations.bandwidth');
    }
}
