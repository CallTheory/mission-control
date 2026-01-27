<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class ConfigEditorController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('config-editor') && $request->user()->currentTeam->utility_config_editor) {
            if ($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }

            return view('utilities.config-editor');
        }
        abort(404);
    }
}
