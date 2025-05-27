<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Exception;
use Illuminate\View\View;

class McpServerController extends Controller
{
    /**
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if(Helpers::isSystemFeatureEnabled('mcp-server')) {
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            return view('system.mcp-server');

        }
        abort(404);

    }
}
