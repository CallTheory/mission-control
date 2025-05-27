<?php

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;

class DirectorySearchController extends Controller
{
    public function __invoke(Request $request)
    {
        if (Helpers::isSystemFeatureEnabled('directory-search') && $request->user()->currentTeam->utility_directory_search) {
            if($request->user()->currentTeam->personal_team === true) {
                abort(403);
            }
            return view('utilities.directory-search');
        }

        abort(404);

    }
}
