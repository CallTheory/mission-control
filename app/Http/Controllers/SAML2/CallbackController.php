<?php

namespace App\Http\Controllers\SAML2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Profile\SsoLinkController;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class CallbackController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $settings = Settings::first();
        if ($settings->saml2_enabled === 1) {
            Config::set('services.saml2.sp_entityid', secure_url('/sso/saml2'));
            if ($settings->saml2_metadata_url) {
                Config::set('services.saml2.metadata', $settings->saml2_metadata_url);
            } elseif ($settings->saml2_metadata_xml) {
                Config::set('services.saml2.metadata', $settings->saml2_metadata_xml);
            } else {
                Config::set('services.saml2.metadata', null);
            }
            Config::set('services.saml2.sp_acs', secure_url('/sso/saml2/callback'));

            Config::set('services.saml2.sp_sign_assertions', $settings->saml2_sp_sign_assertions);

            if ($settings->saml2_sp_sign_assertions === 1) {
                Config::set('services.saml2.sp_certificate', $settings->saml2_sp_certificate);
                Config::set('services.saml2.sp_private_key', $settings->saml2_sp_private_key);

            } else {
                Config::set('services.saml2.sp_certificate', null);
                Config::set('services.saml2.sp_private_key', null);
            }

            try {
                $samlUser = Socialite::driver('saml2')->stateless($settings->saml2_stateless_callback ?? false)->user();

                if (! $samlUser) {
                    throw new Exception('Invalid SAML response: No user data received');
                }

                // The provider validates issuer/recipient/signature/timestamps but NOT
                // the audience. Verify the assertion was minted for *this* SP so an
                // assertion issued for a different SP can't be replayed here.
                $this->validateAudience($samlUser, secure_url('/sso/saml2'));

                Log::info('SAML2 User', [
                    'id' => $samlUser->getId(),
                    'name' => $samlUser->getName(),
                    'email' => $samlUser->getEmail(),
                ]);

                $emailAttribute = null;
                $nameAttribute = null;
                $surnameAttribute = null;

                foreach ($samlUser->getRaw() as $thing) {

                    if ($thing->getName() === 'emailaddress') {
                        $emailAttribute = $thing->getFirstAttributeValue();
                    }

                    if ($thing->getName() === 'givenname') {
                        $nameAttribute = $thing->getFirstAttributeValue();
                    }

                    if ($thing->getName() === 'surname') {
                        $surnameAttribute = $thing->getFirstAttributeValue();
                    }
                    Log::info('SAML2 Attributes', [
                        'name' => $thing->getName(),
                        'value' => $thing->getFirstAttributeValue(),
                    ]);
                }

                if ($emailAttribute === null) {
                    $emailAttribute = $samlUser->getEmail();
                }

                if ($nameAttribute === null) {
                    $nameAttribute = $samlUser->getName();
                }

                if ($surnameAttribute !== null) {
                    $nameAttribute .= ' '.$surnameAttribute;
                }
            } catch (Exception $e) {
                Log::error('SAML2 Authentication Error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return redirect('/login')->withErrors(['SAML2 authentication failed: '.$e->getMessage()]);
            }

            $validator = Validator::make([
                'email' => $emailAttribute,
                'name' => $nameAttribute,
                'id' => $samlUser->getId(),
            ], [
                'email' => 'required|email',
                'name' => 'required|string',
                'id' => 'required',
            ],
                [
                    'email.required' => 'The SAML2 email is required',
                    'email.email' => 'The SAML2 email must be a valid email address',
                    'name.required' => 'The SAML2 name is required',
                    'name.string' => 'The SAML2 name must be a string',
                    'id.required' => 'The SAML2 ID was not found in the response',
                ]);

            if ($validator->fails()) {
                return redirect()->to('/login')->withErrors($validator->errors()->all());
            }

            if (! $this->emailDomainAllowed($emailAttribute)) {
                Log::warning('SAML2 provisioning blocked: email domain not permitted', [
                    'email' => $emailAttribute,
                ]);

                return redirect('/login')->withErrors(['Your account domain is not permitted to sign in.']);
            }

            // A link request started from the profile page: attach the subject id to
            // that account rather than signing anyone in.
            $linkUserId = $request->session()->pull(SsoLinkController::SESSION_KEY);

            if ($linkUserId !== null) {
                return $this->completeAccountLink((int) $linkUserId, $emailAttribute, (string) $samlUser->getId());
            }

            try {
                $user = $this->resolveUser(
                    (string) $samlUser->getId(),
                    $emailAttribute,
                    $nameAttribute,
                    $settings->switch_data_timezone ?? 'UTC',
                );

                if (! $user) {
                    return redirect('/login')->withErrors([
                        'That email address belongs to an account linked to a different identity.',
                    ]);
                }

                if ($user->personalTeam() === null) {
                    $user->ownedTeams()->save(Team::forceCreate([
                        'user_id' => $user->id,
                        'name' => 'Personal Team',
                        'personal_team' => true,
                    ]));
                }

                Auth::login($user, true);

                return redirect('/dashboard');
            } catch (Exception $e) {
                Log::error('SAML2 User Sync Error', [
                    'message' => $e->getMessage(),
                ]);

                return redirect('/login')->withErrors(['Unable to sync SAML2 user']);
            }
        }

        return redirect('/login')->withErrors(['SAML2 is not enabled']);
    }

    /**
     * Resolve the assertion to a local account, binding on the subject id.
     *
     * The subject id is checked first so an IdP identity stays attached to the
     * account it was linked to: someone whose email changes at the IdP keeps their
     * account (and their teams) instead of silently getting a fresh empty one, and
     * unlinking on the profile page genuinely revokes the connection rather than
     * being decorative.
     *
     * Email is still the fallback, because that is how every account linked before
     * this existed has to be found the first time. It is only trusted when the
     * account is *unlinked*: a match on an account already bound to a different
     * subject id returns null rather than reassigning it, so an IdP that can be
     * made to assert someone else's address cannot take over their account.
     *
     * Escape hatch, for an IdP rebuild that reissues every subject id:
     * `php artisan sso:unlink --all` clears the column and puts everyone back on
     * email matching for their next sign-in.
     */
    protected function resolveUser(string $subjectId, string $email, string $name, string $timezone): ?User
    {
        $user = User::where('saml_linked_id', $subjectId)->first();

        if (! $user) {
            $user = User::where('email', $email)->first();

            // Bound to someone else's IdP identity -- do not re-point it.
            if ($user && filled($user->saml_linked_id) && $user->saml_linked_id !== $subjectId) {
                Log::warning('SAML2 sign-in blocked: account is linked to a different subject id', [
                    'user_id' => $user->id,
                ]);

                return null;
            }
        }

        $attributes = [
            'name' => $name,
            'email' => $email,
            'saml_linked_id' => $subjectId,
            // Rotated on every sync so a password captured earlier cannot be reused
            // to bypass the IdP. Predates this change; kept deliberately.
            'password' => Hash::make(bin2hex(openssl_random_pseudo_bytes(64))),
        ];

        if ($user) {
            $user->forceFill($attributes)->save();

            return $user;
        }

        return User::create($attributes + ['timezone' => $timezone]);
    }

    /**
     * Finish a link started from the profile page.
     *
     * The assertion has already been validated by the caller; what is checked
     * here is that it belongs to the person who asked. Requiring both a still
     * logged-in session for that user and a matching email address keeps an
     * assertion for some other account from being pinned onto this one.
     */
    protected function completeAccountLink(int $userId, string $assertionEmail, string $subjectId): RedirectResponse
    {
        $user = User::find($userId);

        if (! $user || Auth::id() !== $user->id) {
            Log::warning('SAML2 account link rejected: no matching authenticated session', [
                'user_id' => $userId,
            ]);

            return redirect('/user/profile')
                ->with('flash.banner', 'Your session expired before the link could be completed. Please try again.')
                ->with('flash.bannerStyle', 'danger');
        }

        if (strcasecmp($user->email, $assertionEmail) !== 0) {
            Log::warning('SAML2 account link rejected: assertion email does not match the account', [
                'user_id' => $user->id,
            ]);

            return redirect('/user/profile')
                ->with('flash.banner', 'That identity provider account uses a different email address, so it was not linked.')
                ->with('flash.bannerStyle', 'danger');
        }

        $user->saml_linked_id = $subjectId;
        $user->save();

        return redirect('/user/profile')
            ->with('flash.banner', 'Your account is now linked to single sign-on.');
    }

    /**
     * Ensure the assertion's AudienceRestriction names this service provider.
     *
     * If the assertion carries any AudienceRestriction, our SP entity id must be
     * among the listed audiences. A missing AudienceRestriction is rejected only
     * when services.saml2.require_audience is enabled.
     *
     * @throws Exception
     */
    protected function validateAudience(mixed $samlUser, string $expectedAudience): void
    {
        $assertion = method_exists($samlUser, 'getAssertion') ? $samlUser->getAssertion() : null;
        $conditions = $assertion?->getConditions();

        $audiences = [];
        if ($conditions !== null) {
            foreach ($conditions->getAllAudienceRestrictions() as $restriction) {
                $audiences = array_merge($audiences, $restriction->getAllAudience() ?? []);
            }
        }

        if (empty($audiences)) {
            if (config('services.saml2.require_audience')) {
                throw new Exception('SAML assertion is missing a required AudienceRestriction');
            }

            Log::warning('SAML2 assertion had no AudienceRestriction; audience not verified', [
                'expected' => $expectedAudience,
            ]);

            return;
        }

        if (! in_array($expectedAudience, $audiences, true)) {
            throw new Exception('SAML assertion audience does not match this service provider');
        }
    }

    /**
     * Gate JIT provisioning by email domain when an allow-list is configured.
     */
    protected function emailDomainAllowed(string $email): bool
    {
        $allowed = array_filter(array_map(
            'trim',
            explode(',', (string) config('services.saml2.allowed_email_domains'))
        ));

        if (empty($allowed)) {
            return true;
        }

        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
        $allowed = array_map('strtolower', $allowed);

        return $domain !== '' && in_array($domain, $allowed, true);
    }
}
