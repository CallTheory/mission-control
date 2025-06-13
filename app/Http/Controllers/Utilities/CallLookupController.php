<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CallLookupController extends Controller
{
    public function __invoke(Request $request, ?string $isCallID = null)
    {
        if (! Helpers::isSystemFeatureEnabled('call-lookup') && $request->user()->currentTeam->utility_call_lookup) {
            abort(404);
        }

        if (! is_null($isCallID)) {
            try {
                $call = new Call(['ISCallId' => $isCallID]);
            } catch (Exception $e) {
                abort(404);
            }

            if ($request->user()->currentTeam->personal_team === true) {
                if ($request->user()->agtId != $call->agtId) {
                    abort(403);
                }
            }

            if (Helpers::allowedAccountAccess(
                $call->ClientNumber ?? '',
                $call->BillingCode ?? '',
                $request->user()->currentTeam->allowed_accounts ?? '',
                $request->user()->currentTeam->allowed_billing ?? ''
            ) !== true) {
                abort(403);
            }
        }

        Session::put('searchTerm', $isCallID);

        return view('utilities.call-lookup')->with('isCallID', $isCallID);
    }
}
