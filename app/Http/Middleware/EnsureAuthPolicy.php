<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\AuthPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        'livewire/*',
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

        if (! app(AuthPolicy::class)->needsTwoFactorEnrollment($user)) {
            return $next($request);
        }

        return redirect()->route('profile.show')->with(
            'flash.banner',
            __('Two-factor authentication is required. Set it up below to continue.')
        );
    }
}
