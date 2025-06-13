<?php

namespace App\Http\Controllers\SAML2;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;

// use Laravel\Socialite\Facades\Socialite;

class SingleLogoutServiceController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): void
    {
        // SLO introduces a potential security issue via cookies
        // Disabling until needed
        // $response = Socialite::driver('saml2')->logoutResponse();
        abort(404);
    }
}
