<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use App\Models\DataSource;
use Illuminate\Http\Request;

class CloudFaxingController extends Controller
{
    public function __invoke(Request $request, $provider = 'mfax')
    {
        // Supervisor access (roles admin/manager/supervisor or a -SUP agent) is
        // encoded in the utility.cloud_faxing capability and its suffix rules.
        $this->authorizeUtility(Utility::CloudFaxing);

        $datasource = DataSource::first();
        $mfaxEnabled = (bool) $datasource?->mfax_enabled;
        $ringcentralEnabled = (bool) $datasource?->ringcentral_enabled;

        if ($provider === 'ringcentral' && ! $ringcentralEnabled) {
            if ($mfaxEnabled) {
                return redirect('/utilities/cloud-faxing');
            }
            abort(404);
        }

        if ($provider !== 'ringcentral' && ! $mfaxEnabled) {
            if ($ringcentralEnabled) {
                return redirect('/utilities/cloud-faxing/ringcentral');
            }
            abort(404);
        }

        if ($provider === 'ringcentral') {
            return view('utilities.cloud-faxing-ringcentral', compact('mfaxEnabled', 'ringcentralEnabled'));
        }

        return view('utilities.cloud-faxing', compact('mfaxEnabled', 'ringcentralEnabled'));
    }
}
