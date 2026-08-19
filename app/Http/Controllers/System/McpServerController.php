<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class McpServerController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::SystemAccess->value);
        abort_unless(Helpers::isSystemFeatureEnabled('mcp-server'), 404);

        return view('system.mcp-server');
    }
}
