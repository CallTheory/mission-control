<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Exception;

class ApiGatewayController extends Controller
{
    /**
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }
        if(Helpers::isSystemFeatureEnabled('api-gateway')) {
            return view('system.api-gateway');
        }
        abort(404);
    }
}
