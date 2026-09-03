<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use Illuminate\View\View;
use Livewire\Component;

class CsvExport extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    public function render(): View
    {
        return view('livewire.system.csv-export');
    }
}
