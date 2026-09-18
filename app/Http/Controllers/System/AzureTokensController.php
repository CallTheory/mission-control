<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AzureTokensController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Like Observability, this is operator infrastructure rather than a licensed
        // per-team utility, so there is deliberately no feature-flag check here.
        $this->authorize(Capability::SystemAzureTokens->value);

        return view('system.azure-tokens');
    }
}
