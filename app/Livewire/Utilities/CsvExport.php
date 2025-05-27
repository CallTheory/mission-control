<?php

namespace App\Livewire\Utilities;

use Illuminate\View\View;
use Livewire\Component;

class CsvExport extends Component
{
    public function render(): View
    {
        return view('livewire.utilities.csv-export');
    }
}
