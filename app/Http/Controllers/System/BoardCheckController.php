<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoardCheckController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::SystemAccess->value);
        abort_unless(Helpers::isSystemFeatureEnabled('board-check'), 404);

        return view('system.board-check');
    }
}
