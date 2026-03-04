<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class CsvExportController extends Controller
{
    public function index(Request $request)
    {
        $this->ensureAccess($request);

        return view('utilities.csv-export');
    }

    public function history(Request $request)
    {
        $this->ensureAccess($request);

        return view('utilities.csv-export-history');
    }

    private function ensureAccess(Request $request): void
    {
        if (! Helpers::isSystemFeatureEnabled('csv-export') || ! $request->user()->currentTeam->utility_csv_export) {
            abort(404);
        }

        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }
    }
}
