<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Exception;

class UsersController extends Controller
{
    /**
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if ($request->user()->currentTeam->personal_team === false) {
            if (
                $request->user()->hasTeamRole($request->user()->currentTeam, 'admin')
            ) {
                return view('system.users');
            }
        }

        abort(403);
    }
}
