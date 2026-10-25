<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AuthPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces enrolment when the SSO-or-2FA policy is on and a user has neither.
 *
 * Enforced after login rather than at it, on purpose: a user who has not set up
 * 2FA yet still has to be able to get in far enough to set it up. Blocking at
 * the login form would make the policy unsatisfiable for everyone who did not
 * already comply on the day it was switched on.
 */
class EnsureAuthPolicy
{
    /**
     * Routes that stay reachable while a user is out of compliance: the 2FA
     * setup flow itself, the profile page that hosts it, the password flows a
     * locked-out user needs, and the way out.
     *
     * @var list<string>
     */
    private const ALLOWED = [
        'two-factor.*',
        'profile.*',
        'password.*',
        'logout',
        'user/two-factor-*',
        'user/profile-information',
        'user/password',
    ];

    /**
     * Livewire components a user out of compliance may still drive: the profile page
     * that hosts 2FA setup, and the layout around it, whose components Livewire can
     * bundle into the same update request as the click on "Enable".
     *
     * @var list<string>
     */
    private const ALLOWED_COMPONENTS = [
        'profile.*',
        'navigation-menu',
        'menu-break',
        'notifications',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        if ($request->routeIs(self::ALLOWED) || $request->is(self::ALLOWED)) {
            return $next($request);
        }

        if ($this->isEnrolmentUpdate($request)) {
            return $next($request);
        }

        if (! app(AuthPolicy::class)->needsTwoFactorEnrollment($user)) {
            return $next($request);
        }

        return redirect()->route('profile.show')->with(
            'flash.banner',
            __('Two-factor authentication is required. Set it up below to continue.')
        );
    }

    /**
     * Whether this is a Livewire update touching only the components above.
     *
     * Every Livewire action posts to one endpoint -- under Livewire 4,
     * /livewire-{hash}/update, which the old 'livewire/*' path never matched, so
     * clicking "Enable" was redirected and 2FA could not be set up at all. Allowing
     * the endpoint outright would instead let an unenrolled user run any component's
     * actions, so the payload's component names are checked. The names come from
     * snapshots Livewire checksums when it handles the request, so one cannot be
     * relabelled to slip through here.
     */
    private function isEnrolmentUpdate(Request $request): bool
    {
        if (! $request->routeIs('*livewire.update')) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $name = is_array($snapshot) ? ($snapshot['memo']['name'] ?? null) : null;

            if (! is_string($name) || ! Str::is(self::ALLOWED_COMPONENTS, $name)) {
                return false;
            }
        }

        return true;
    }
}
