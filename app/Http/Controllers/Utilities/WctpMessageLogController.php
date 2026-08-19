<?php

declare(strict_types=1);

namespace App\Http\Controllers\Utilities;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Models\Stats\Helpers;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WctpMessageLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $team = $request->user()->currentTeam;

        // WCTP management pages deny with 403 across the board (matching the
        // AuthorizesWctpManagement Livewire trait), rather than 404.
        abort_if($team === null || $team->personal_team === true, 403);

        abort_unless(
            Helpers::isSystemFeatureEnabled('wctp-gateway')
                && (bool) $team->utility_wctp_gateway
                && $request->user()->hasCapability(Capability::UtilityWctpGateway, $team),
            403
        );

        return view('utilities.wctp-messages');
    }
}
