<?php

namespace App\Http\Controllers\SAML2;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Facades\Socialite;

class MetadataController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): Socialite
    {

        $settings = Settings::first();

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

        if ($settings->saml2_enabled === 1) {
            return Socialite::driver('saml2')->getServiceProviderMetadata()->withHeaders([
                'content-type' => 'application/xml',
            ]);
        }
        abort(404);
    }
}
