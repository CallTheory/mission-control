<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CsvExportController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if (Helpers::isSystemFeatureEnabled('csv-export')) {
            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('system.csv-export');

        }
        abort(404);

    }
}
