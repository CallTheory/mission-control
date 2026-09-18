<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use App\Models\User;

/**
 * Who may reach a call -- the page, its recording, and its screen capture.
 *
 * One class because the rule was previously duplicated and the copies disagreed:
 * CallLookupController decided who may open a call PAGE while the two media controllers
 * each carried a narrower copy, so an agent could open their own call and then get a 403
 * on every .wav the player asked for.
 *
 * The rule follows the two kinds of team:
 *
 *  - A PERSONAL team has no account allow-lists and never will, so it is scoped by agent
 *    instead: you reach a call you handled. Nothing here is configurable for it.
 *
 *  - A SHARED team is scoped by allowed_accounts / allowed_billing. A team with neither
 *    list AND without `unrestricted_accounts` set has not had its scope decided, and is
 *    withheld rather than shown everything: an empty list means "no restriction"
 *    throughout this application, and that is only a safe reading when someone chose it.
 *    Teams that predate the column were backfilled to unrestricted, so this bites only
 *    teams created and never configured.
 */
final class CallAccess
{
    /**
     * Fail the request before the call is loaded, when no call could be allowed.
     *
     * Loading a Call means a round trip to the Intelligent Series switch database, so a
     * user who can never be permitted is turned away first.
     */
    public static function authorizeTeam(?User $user): void
    {
        abort_unless(self::couldAllowAnyCall($user), 403);
    }

    /**
     * Fail the request unless this user may reach this specific call.
     */
    public static function authorizeCall(?User $user, Call $call): void
    {
        abort_unless(self::allows($user, $call), 403);
    }

    public static function allows(?User $user, Call $call): bool
    {
        $team = $user?->currentTeam;

        if ($team === null) {
            return false;
        }

        if ($team->personal_team === true) {
            // Your own calls, identified by agent id. A user with no agtId has no calls
            // of their own and so reaches nothing.
            return $user->agtId !== null && (string) $user->agtId === (string) $call->agtId;
        }

        if (! $team->hasDecidedAccountScope()) {
            return false;
        }

        // Empty lists fall through to true here, which is the same reading the listing
        // queries take -- correct now that reaching this line means someone chose it.
        return Helpers::allowedAccountAccess(
            (string) ($call->ClientNumber ?? ''),
            (string) ($call->BillingCode ?? ''),
            (string) ($team->allowed_accounts ?? ''),
            (string) ($team->allowed_billing ?? ''),
        );
    }

    /**
     * Whether this user could reach ANY call -- the half of the rule that does not need
     * the call itself, so it can run before the switch-DB lookup.
     */
    private static function couldAllowAnyCall(?User $user): bool
    {
        $team = $user?->currentTeam;

        if ($team === null) {
            return false;
        }

        // Personal teams are decided per call, by agent, so the answer waits for the
        // call -- except that a user with no agent id never matches one.
        if ($team->personal_team === true) {
            return $user->agtId !== null;
        }

        return $team->hasDecidedAccountScope();
    }
}
