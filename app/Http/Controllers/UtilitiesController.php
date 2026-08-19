<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UtilitiesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::UtilitiesAccess->value);

        return view('utilities');
    }
}
