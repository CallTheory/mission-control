<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use Illuminate\View\View;
use Livewire\Component;

class McpProtocolTest extends Component
{
    public function render(): View
    {
        return view('livewire.utilities.mcp-protocol-test');
    }
}