<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientAccountsController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        if ($request->user()->currentTeam->personal_team === true) {
            abort(403);
        }

        return view('accounts.client-accounts');
    }
}
