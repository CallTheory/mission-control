<?php

namespace App\Http\Controllers\SAML2;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DownloadCertificateController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): Response
    {
        if (! $request->user()->currentTeam->personal_team && $request->user()->hasTeamRole($request->user()->currentTeam, 'admin')) {
            $settings = Settings::firstOrFail();
            try {
                if ($settings->saml2_enabled && strlen(decrypt($settings->saml2_sp_certificate))) {

                    $headers = [
                        'content-type' => 'application/x-x509-ca-cert',
                        'content-disposition' => 'attachment; filename="mission_control_saml_sp_cert.cer"',
                    ];

                    return response(decrypt($settings->saml2_sp_certificate), 200, $headers);
                } else {
                    abort(404);
                }
            } catch (Exception $e) {
                abort(404);
            }
        }
        abort(403);
    }
}
