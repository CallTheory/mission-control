<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\Capability;
use App\Models\Stats\Helpers;
use App\Models\User;

/**
 * Who may reach the WCTP gateway section.
 *
 * The gateway is installation-wide configuration -- carrier credentials, phone
 * numbers, enterprise hosts -- not a per-team utility, so there is no team feature
 * flag and no team scoping here: the only questions are whether the gateway is
 * switched on at all and whether this user is an administrator.
 *
 * Capabilities are still resolved through the acting team's roles (as they are for
 * every other system screen), which is what `hasCapability()` does; what changed is
 * that the DATA is no longer partitioned by team.
 *
 * One class rather than a trait because both the controllers and the Livewire
 * components inside them have to apply the same rule -- Livewire does not re-run a
 * controller's authorization on `POST /livewire/update`, and it swallows `abort()`
 * raised in a component's `mount()` on a full-page render. So the controller is the
 * real page gate and the component guards are defence in depth; they must agree.
 */
final class WctpSectionAccess
{
    /**
     * Fail the request unless this user may use the given part of the section.
     */
    public static function authorize(Capability $capability): void
    {
        // Off entirely when the system feature is off: 404, matching how the rest of
        // System Settings treats a disabled feature.
        abort_unless(Helpers::isSystemFeatureEnabled('wctp-gateway'), 404);

        abort_unless(self::userAllows(auth()->user(), $capability), 403);
    }

    /**
     * Fail the request unless this user may use SOME part of the section -- the gate
     * for the section index, which then shows only the links the user can follow.
     */
    public static function authorizeAny(): void
    {
        abort_unless(Helpers::isSystemFeatureEnabled('wctp-gateway'), 404);

        $user = auth()->user();

        abort_unless(
            self::userAllows($user, Capability::WctpManage)
                || self::userAllows($user, Capability::WctpMessages),
            403
        );
    }

    /**
     * Whether the acting user may use the given part of the section -- for deciding
     * which links to render, rather than for gating a request.
     */
    public static function allows(Capability $capability): bool
    {
        return Helpers::isSystemFeatureEnabled('wctp-gateway')
            && self::userAllows(auth()->user(), $capability);
    }

    /**
     * Whether the user holds any part of the section, i.e. whether the section is
     * worth showing in navigation at all.
     */
    public static function allowsAny(): bool
    {
        return self::allows(Capability::WctpManage)
            || self::allows(Capability::WctpMessages);
    }

    private static function userAllows(?User $user, Capability $capability): bool
    {
        return $user !== null && $user->hasCapability($capability);
    }
}
