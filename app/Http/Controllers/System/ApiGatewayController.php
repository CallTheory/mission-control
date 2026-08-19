<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiGatewayController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::SystemAccess->value);
        abort_unless(Helpers::isSystemFeatureEnabled('api-gateway'), 404);

        return view('system.api-gateway');
    }
}
