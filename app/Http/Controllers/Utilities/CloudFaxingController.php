<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
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

        // The source arrives as a query parameter rather than a path segment: the route's
        // one optional segment is already the provider, /utilities/cloud-faxing/is2 would
        // be ambiguous with a provider name, and the buildup alert builds its link by
        // string concatenation.
        $sources = FaxSpoolSource::query()->enabled()->orderBy('name')->get();
        $sourceKey = FaxSpoolSource::resolveKey(
            $request->query('source') === null ? null : (string) $request->query('source'),
            $provider === 'ringcentral' ? 'ringcentral' : 'mfax',
        );

        $view = $provider === 'ringcentral' ? 'utilities.cloud-faxing-ringcentral' : 'utilities.cloud-faxing';

        return view($view, compact('mfaxEnabled', 'ringcentralEnabled', 'sources', 'sourceKey'));
    }
}
