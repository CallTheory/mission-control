<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\System\Settings;
use App\Models\User;

/**
 * The two authentication policies an admin can switch on, in one place.
 *
 * Both are off by default. Everything that enforces them -- the login action,
 * the middleware, the profile page -- asks this class rather than reading the
 * settings itself, so there is one definition of each rule to reason about.
 */
class AuthPolicy
{
    /**
     * Rule A: this account may only sign in through the identity provider.
     *
     * Deliberately false when SAML is switched off system-wide. Without that
     * interlock, disabling SAML would strand every linked user behind a login
     * they can no longer reach -- and the admin who could undo it is usually
     * linked too.
     *
     * A user belonging to several teams is enforced unless *every* one of them
     * is exempt, so being added to a third-party team cannot quietly lift the
     * requirement everywhere else.
     */
    public function mustUseSso(User $user): bool
    {
        $settings = Settings::first();

        if (! $settings || ! $settings->auth_enforce_linked_sso) {
            return false;
        }

        if ((int) $settings->saml2_enabled !== 1) {
            return false;
        }

        if (blank($user->saml_linked_id)) {
            return false;
        }

        return ! $this->everyTeamIsExempt($user);
    }

    /**
     * Rule B: this account has neither a linked identity nor 2FA, and the
     * policy says it must have one of them.
     *
     * A linked account satisfies the policy on its own -- rule A already forces
     * it through the IdP, which is where its second factor lives.
     */
    public function needsTwoFactorEnrollment(User $user): bool
    {
        $settings = Settings::first();

        if (! $settings || ! $settings->auth_require_sso_or_2fa) {
            return false;
        }

        if (filled($user->saml_linked_id)) {
            return false;
        }

        return ! $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * True only when the user has at least one team and all of them are exempt.
     * A user with no teams is not exempt.
     */
    private function everyTeamIsExempt(User $user): bool
    {
        $teams = $user->allTeams();

        if ($teams->isEmpty()) {
            return false;
        }

        return $teams->every(fn ($team): bool => (bool) $team->sso_exempt);
    }
}
