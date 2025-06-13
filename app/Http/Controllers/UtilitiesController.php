<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UtilitiesController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {

        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }

        if ($request->user()->hasTeamRole($request->user()->currentTeam, 'admin') ||
            $request->user()->hasTeamRole($request->user()->currentTeam, 'manager') ||
            $request->user()->hasTeamRole($request->user()->currentTeam, 'supervisor') ||
            $request->user()->hasTeamRole($request->user()->currentTeam, 'dispatcher')
        ) {

            return view('utilities');
        }

        abort(403);
    }
}
