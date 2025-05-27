<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class WctpGatewayController extends Controller
{
    public function __invoke( Request $request){
        if(Helpers::isSystemFeatureEnabled('wctp-gateway') && $request->user()->currentTeam->utility_wctp_gateway){

            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('utilities.wctp-gateway');
        }
        abort(404);
    }
}
