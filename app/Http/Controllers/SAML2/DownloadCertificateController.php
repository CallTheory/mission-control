<?php

namespace App\Http\Controllers\SAML2;

use App\Enums\Capability;
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
        $this->authorize(Capability::SystemAccess->value);

        $settings = Settings::firstOrFail();
        try {
            // filled() rather than strlen(): the column is null when no SP
            // certificate has been generated, and strlen(null) is deprecated.
            if ($settings->saml2_enabled && filled($settings->saml2_sp_certificate)) {

                $headers = [
                    'content-type' => 'application/x-x509-ca-cert',
                    'content-disposition' => 'attachment; filename="mission_control_saml_sp_cert.cer"',
                ];

                return response($settings->saml2_sp_certificate, 200, $headers);
            } else {
                abort(404);
            }
        } catch (Exception $e) {
            abort(404);
        }
    }
}
