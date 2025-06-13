<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CloudFaxingController extends Controller
{
    public function __invoke(Request $request, $provider = 'mfax')
    {
        if (Helpers::isSystemFeatureEnabled('cloud-faxing') && $request->user()->currentTeam->utility_cloud_faxing) {
            $user = Auth::user();
            $agent = null;

            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

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

            if ($provider === 'ringcentral') {
                return view('utilities.cloud-faxing-ringcentral');
            } else {
                return view('utilities.cloud-faxing');
            }
        }

        abort(404);
    }
}
