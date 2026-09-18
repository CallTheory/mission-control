<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use App\Support\UtilityAvailability;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UtilitiesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::UtilitiesAccess->value);

        // The tiles each gate themselves, so the view needs to be told why the
        // grid is empty -- the remedy differs per cause.
        $availability = new UtilityAvailability($request->user());

        return view('utilities', [
            'emptyReason' => $availability->reason(),
            'fixRoute' => $availability->fixRoute(),
        ]);
    }
}
