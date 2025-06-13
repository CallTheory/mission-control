<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoardReportController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('board-check') && $request->user()->currentTeam->utility_board_check) {
            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            $user = Auth::user();
            $agent = null;

            if (! is_null($user)) {
                if ($request->user()->currentTeam->personal_team === true) {
                    $supervisor = false;
                } else {
                    $agent = $user->getIntelligentAgent();
                    $supervisor = str_contains($agent->Name ?? '', '-SUP') || $user->hasTeamRole($user->currentTeam, 'admin') || $user->hasTeamRole($user->currentTeam, 'manager') || $user->hasTeamRole($user->currentTeam, 'supervisor');
                }
            } else {
                $supervisor = false;
            }
            if (! $supervisor) {
                abort(403);
            }

            return view('utilities.board-report');
        }
        abort(404);
    }
}
