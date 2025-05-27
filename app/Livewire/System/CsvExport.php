<?php

namespace App\Livewire\System;

use Illuminate\View\View;
use Livewire\Component;

class CsvExport extends Component
{
    public function render(): View
    {
        return view('livewire.system.csv-export');
    }
}
