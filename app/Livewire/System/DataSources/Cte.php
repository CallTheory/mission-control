<?php

namespace App\Livewire\System\DataSources;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use Illuminate\View\View;
use Livewire\Component;

class Cte extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemDataSources;
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.cte');
    }
}
