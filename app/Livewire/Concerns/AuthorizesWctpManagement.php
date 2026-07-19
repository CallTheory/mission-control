<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\Stats\Helpers;
use App\Models\Team;

/**
 * Shared authorization + tenancy helpers for the WCTP management utilities.
 *
 * WCTP host/message management is a non-personal-team utility gated by the
 * `wctp-gateway` system feature and the team's `utility_wctp_gateway` flag,
 * mirroring App\Http\Controllers\Utilities\WctpGatewayController. Every WCTP
 * management component must authorize on mount and scope its queries to the
 * acting team so one tenant can never see or mutate another tenant's hosts or
 * traffic.
 */
trait AuthorizesWctpManagement
{
    protected function authorizeWctpManagement(): void
    {
        $team = $this->currentTeam();

        if ($team === null
            || $team->personal_team === true
            || ! Helpers::isSystemFeatureEnabled('wctp-gateway')
            || ! $team->utility_wctp_gateway) {
            abort(403);
        }
    }

    protected function currentTeam(): ?Team
    {
        return auth()->user()?->currentTeam;
    }

    protected function currentTeamId(): int
    {
        return (int) $this->currentTeam()->id;
    }
}
