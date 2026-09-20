<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starts the "link my account" leg of the SAML flow.
 *
 * All this does is mark the session with who asked, then hand off to the normal
 * redirect controller. SAML2\CallbackController reads the marker and attaches
 * the subject id to that account instead of signing anyone in.
 */
class SsoLinkController extends Controller
{
    /** Session key carrying the id of the user waiting for a link. */
    public const SESSION_KEY = 'saml2.link_user_id';

    public function __invoke(Request $request): RedirectResponse
    {
        if (Settings::first()?->saml2_enabled !== 1) {
            return redirect()->route('profile.show')
                ->with('flash.banner', 'Single sign-on is not enabled.')
                ->with('flash.bannerStyle', 'danger');
        }

        $request->session()->put(self::SESSION_KEY, $request->user()->id);

        return redirect()->route('sso.saml2.redirect');
    }
}
