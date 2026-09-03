<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObservabilityController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Observability is operator infrastructure, not a licensed per-team
        // utility, so there is deliberately no feature-flag check here.
        $this->authorize(Capability::SystemObservability->value);

        return view('system.observability');
    }
}
