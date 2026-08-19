<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissionsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::AdminManageRoles->value);

        return view('system.permissions');
    }
}
