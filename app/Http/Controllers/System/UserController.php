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
        if ($request->user()->currentTeam->personal_team === false) {
            if (
                $request->user()->hasTeamRole($request->user()->currentTeam, 'admin')
            ) {
                return view('system.user')->with('user', $user);
            }
        }
        abort(403);
    }
}
