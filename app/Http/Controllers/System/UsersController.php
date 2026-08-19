<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::AdminManageUsers->value);

        return view('system.users');
    }
}
