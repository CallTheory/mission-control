<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadTBSReport extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('card-processing') && $request->user()->currentTeam->utility_card_processing) {

            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            if (session()->has('utilities.card-processing.export_file')) {
                return Storage::download(session()->get('utilities.card-processing.export_file'));
            }
        }
        abort(404);
    }
}
