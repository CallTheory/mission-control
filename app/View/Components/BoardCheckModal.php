<?php

namespace App\View\Components;

use Closure;
use Illuminate\View\Component;
use Illuminate\View\View;

class BoardCheckModal extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|Closure|string
     */
    public function render(): View|Closure|string
    {
        return view('components.board-check-modal');
    }
}
