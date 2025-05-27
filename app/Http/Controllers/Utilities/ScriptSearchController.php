<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class ScriptSearchController extends Controller
{
    public function __invoke(Request $request){
        if(Helpers::isSystemFeatureEnabled('script-search')&& $request->user()->currentTeam->utility_script_search){

            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('utilities.script-search');
        }
        abort(404);
    }
}
