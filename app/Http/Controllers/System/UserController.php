<?php

namespace App\Http\Controllers\System;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __invoke(Request $request, User $user): View
    {
        $this->authorize(Capability::AdminManageUsers->value);

        // The target user must belong to the admin's current team — otherwise
        // an admin could enumerate users across other tenants by id.
        abort_unless($user->belongsToTeam($request->user()->currentTeam), 403);

        return view('system.user')->with('user', $user);
    }
}
