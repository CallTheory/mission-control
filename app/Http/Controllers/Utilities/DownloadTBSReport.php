<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DownloadTBSReport extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorizeUtility(Utility::CardProcessing);

        if (session()->has('utilities.card-processing.export_file')) {
            return Storage::download(session()->get('utilities.card-processing.export_file'));
        }

        abort(404);
    }
}
