<?php

namespace App\Http\Controllers\SAML2;

use App\Http\Controllers\Controller;
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
                Config::set('services.saml2.metadata', decrypt($settings->saml2_metadata_xml));
            } else {
                Config::set('services.saml2.metadata', null);
            }
            Config::set('services.saml2.sp_acs', secure_url('/sso/saml2/callback'));

            Config::set('services.saml2.sp_sign_assertions', $settings->saml2_sp_sign_assertions);

            if ($settings->saml2_sp_sign_assertions === 1) {
                Config::set('services.saml2.sp_certificate', decrypt($settings->saml2_sp_certificate));
                Config::set('services.saml2.sp_private_key', decrypt($settings->saml2_sp_private_key));

            } else {
                Config::set('services.saml2.sp_certificate', null);
                Config::set('services.saml2.sp_private_key', null);
            }

            try {
                $samlUser = Socialite::driver('saml2')->stateless($settings->saml2_stateless_callback ?? false)->user();
                
                if (!$samlUser) {
                    throw new Exception('Invalid SAML response: No user data received');
                }

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
                return redirect('/login')->withErrors(['SAML2 authentication failed: ' . $e->getMessage()]);
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

            try {
                // rotate secure passwords every time a user syncs?
                $bytes = openssl_random_pseudo_bytes(64);
                $password = bin2hex($bytes);

                $user = User::updateOrCreate([
                    'email' => $emailAttribute,
                ], [
                    'name' => $nameAttribute,
                    'timezone' => $settings->switch_data_timezone ?? 'UTC',
                    'password' => Hash::make($password),
                    'saml_linked_id' => $samlUser->getId(),
                ]);

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
}
