<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\InboundEmail;
use App\Models\InboundEmailRules;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class InboundEmailController extends Controller
{
    public function __invoke(Request $request)
    {
        if(Helpers::isSystemFeatureEnabled('inbound-email') && $request->user()->currentTeam->utility_inbound_email) {
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            if (
                $request->user()->hasTeamRole($request->user()->currentTeam, 'admin') ||
                $request->user()->hasTeamRole($request->user()->currentTeam, 'manager') ||
                $request->user()->hasTeamRole($request->user()->currentTeam, 'supervisor') ||
                $request->user()->hasTeamRole($request->user()->currentTeam, 'dispatcher')
            ) {
                $emails = InboundEmail::orderBy('id', 'desc')->get();
                $rules = InboundEmailRules::all();

                return view('utilities.inbound-email')->with('emails', $emails)->with('rules', $rules);
            }else
            {
                abort(403);
            }
        }
        abort(404);

    }
}
