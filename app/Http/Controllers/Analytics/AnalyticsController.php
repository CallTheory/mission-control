<?php

namespace App\Http\Controllers\Analytics;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
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
                $request->user()->hasTeamRole($request->user()->currentTeam, 'admin') ||
                $request->user()->hasTeamRole($request->user()->currentTeam, 'manager')
            ) {
                return view('analytics');
            }
        }

        abort(403);
    }
}
