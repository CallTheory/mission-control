<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoardCheckController extends Controller
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
                    $dispatcher = false;
                    $supervisor = false;
                } else {
                    $agent = $user->getIntelligentAgent();
                    $dispatcher = str_contains($agent->Name ?? '', '-DISP') || str_contains($agent->Name ?? '', '-SUP') || $user->hasTeamRole($user->currentTeam, 'dispatcher');
                    $supervisor = str_contains($agent->Name ?? '', '-SUP') || $user->hasTeamRole($user->currentTeam, 'admin') || $user->hasTeamRole($user->currentTeam, 'manager') || $user->hasTeamRole($user->currentTeam, 'supervisor');
                }
            } else {
                $dispatcher = false;
                $supervisor = false;
            }
            if (! $dispatcher && ! $supervisor) {
                abort(403);
            }

            return view('utilities.board-check');
        }
        abort(404);
    }
}
