<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CsvExportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeUtility(Utility::CsvExport);

        return view('utilities.csv-export');
    }

    public function history(Request $request)
    {
        $this->authorizeUtility(Utility::CsvExport);

        return view('utilities.csv-export-history');
    }
}
