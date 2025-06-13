<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class McpServerController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('mcp-server') && $request->user()->currentTeam->utility_mcp_server) {
            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('utilities.mcp-server');

        }
        abort(404);

    }
}
