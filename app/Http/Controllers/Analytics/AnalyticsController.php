<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
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
