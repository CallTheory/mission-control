<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SamlSettingsController extends Controller
{
    /**
     * @throws Exception
     */
    public function __invoke(Request $request): View
    {
        return view('system.saml-settings');
    }
}
