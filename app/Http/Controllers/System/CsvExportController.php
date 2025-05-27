<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Exception;
use Illuminate\View\View;

class CsvExportController extends Controller
{
    /**
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if(Helpers::isSystemFeatureEnabled('csv-export')) {
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            return view('system.csv-export');

        }
        abort(404);

    }
}
