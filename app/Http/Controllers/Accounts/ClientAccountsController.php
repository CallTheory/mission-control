<?php

namespace App\Http\Controllers\Accounts;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientAccountsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize(Capability::AccountsView->value);

        return view('accounts.client-accounts');
    }
}
