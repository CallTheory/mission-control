<?php

namespace App\Http\Controllers;

use App\Enums\Utility;
use App\Models\Stats\Helpers;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Gate;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * A utility whose system feature flag or per-team flag is off looks like it
     * does not exist: abort 404. Presence-but-not-permitted is handled by the
     * capability Gate (403).
     */
    protected function abortUnlessUtilityEnabled(Utility $utility): void
    {
        abort_unless(
            Helpers::isSystemFeatureEnabled($utility->systemFlag())
                && (bool) request()->user()?->currentTeam?->{$utility->teamColumn()},
            404
        );
    }

    /**
     * Standard utility guard: 404 when the feature/team flag is off, otherwise
     * a 403 unless the user has the utility's capability (which also blocks
     * personal teams). Suffix-rule grants are honored by the Gate.
     */
    protected function authorizeUtility(Utility $utility): void
    {
        $this->abortUnlessUtilityEnabled($utility);

        Gate::authorize($utility->capability()->value);
    }
}
