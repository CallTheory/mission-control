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
        $provider = $provider === 'ringcentral' ? 'ringcentral' : 'mfax';

        // Only the servers that can feed *this* provider. The two seeded sources are the
        // original provider-named directories, so passing every enabled source drew a
        // second row of "mFax | RingCentral" underneath the provider tabs that already
        // say exactly that — which reads like a setting rather than the view filter it is.
        $sources = FaxSpoolSource::query()
            ->enabled()
            ->where(fn ($query) => $query->whereNull('pinned_provider')->orWhere('pinned_provider', $provider))
            ->orderBy('name')
            ->get();

        $sourceKey = FaxSpoolSource::resolveKey(
            $request->query('source') === null ? null : (string) $request->query('source'),
            $provider,
            $sources->pluck('key')->all(),
        );

        $view = $provider === 'ringcentral' ? 'utilities.cloud-faxing-ringcentral' : 'utilities.cloud-faxing';

        return view($view, compact('mfaxEnabled', 'ringcentralEnabled', 'sources', 'sourceKey'));
    }
}
