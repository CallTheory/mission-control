<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class CsvExportController extends Controller
{
    public function __invoke(Request $request)
    {
        if(Helpers::isSystemFeatureEnabled('csv-export') && $request->user()->currentTeam->utility_csv_export) {
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            return view('utilities.csv-export');

        }
        abort(404);

    }
}
