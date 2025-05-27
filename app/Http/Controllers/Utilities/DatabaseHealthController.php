<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class DatabaseHealthController extends Controller
{
    public function __invoke(Request $request){

        if(Helpers::isSystemFeatureEnabled('database-health') && $request->user()->currentTeam->utility_database_health){

            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            return view('utilities.database-health');
        }

        abort(404);

    }
}
