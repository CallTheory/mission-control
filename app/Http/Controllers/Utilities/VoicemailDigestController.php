<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class VoicemailDigestController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAccess($request);

        return view('utilities.voicemail-digest');
    }

    public function history(Request $request)
    {
        $this->ensureAccess($request);

        return view('utilities.voicemail-digest-history');
    }

    private function ensureAccess(Request $request): void
    {
        if (! Helpers::isSystemFeatureEnabled('voicemail-digest') || ! $request->user()->currentTeam->utility_voicemail_digest) {
            abort(404);
        }

        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }
    }
}
