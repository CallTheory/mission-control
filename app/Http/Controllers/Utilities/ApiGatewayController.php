<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Stats\Helpers;

class ApiGatewayController extends Controller
{
    public function __invoke(Request $request)
    {
        if(Helpers::isSystemFeatureEnabled('api-gateway') && $request->user()->currentTeam->utility_api_gateway){
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('utilities.api-gateway');
        }
        abort(404);
    }
}
