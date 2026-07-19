<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request, User $user): View
    {
        $currentTeam = $request->user()->currentTeam;

        if ($currentTeam->personal_team === false
            && $request->user()->hasTeamRole($currentTeam, 'admin')
            // The target user must belong to the admin's current team — otherwise
            // an admin could enumerate users across other tenants by id.
            && $user->belongsToTeam($currentTeam)
        ) {
            return view('system.user')->with('user', $user);
        }

        abort(403);
    }
}
