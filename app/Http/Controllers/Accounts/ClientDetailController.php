<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Stats\Clients\Client;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientDetailController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request, $client_number): View
    {
        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }

        try {
            $client = new Client(['client_number' => $client_number]);
        } catch (Exception $e) {
            abort(404);
        }

        if (Helpers::allowedAccountAccess(
            $client->ClientNumber,
            $client->BillingCode ?? '',
            $request->user()->currentTeam->allowed_accounts ?? '',
            $request->user()->currentTeam->allowed_billing ?? ''
        ) !== true) {
            abort(403);
        }

        return view('accounts.client')->with('client_number', $client_number);
    }
}
