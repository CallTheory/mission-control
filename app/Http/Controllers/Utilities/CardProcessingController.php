<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class CardProcessingController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('card-processing') && $request->user()->currentTeam->utility_card_processing) {

            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            if (
                $request->user()->hasTeamRole($request->user()->currentTeam, 'admin') ||
                $request->user()->hasTeamRole($request->user()->currentTeam, 'manager')
            ) {
                return view('utilities.card-processing');
            }
        }

        abort(404);

    }
}
