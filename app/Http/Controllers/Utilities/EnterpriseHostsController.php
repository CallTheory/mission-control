<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnterpriseHostsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $team = $request->user()->currentTeam;

        if ($team->personal_team === true) {
            abort(403);
        }

        if (Helpers::isSystemFeatureEnabled('wctp-gateway') && $team->utility_wctp_gateway) {
            return view('utilities.enterprise-hosts');
        }

        abort(403);
    }
}
