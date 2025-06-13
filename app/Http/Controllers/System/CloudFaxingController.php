<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CloudFaxingController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }
        if (Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return view('system.cloud-faxing');
        }
        abort(404);

    }
}
