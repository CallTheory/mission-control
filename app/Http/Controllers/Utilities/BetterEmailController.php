<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class BetterEmailController extends Controller
{
    public function __invoke(Request $request){

        if(Helpers::isSystemFeatureEnabled('better-emails') && $request->user()->currentTeam->utility_better_emails) {
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            return view('utilities.better-emails');
        }
        abort(404);
    }
}
