<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class VoicemailDigestController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('voicemail-digest') && $request->user()->currentTeam->utility_voicemail_digest) {
            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('utilities.voicemail-digest');
        }
        abort(404);
    }
}
