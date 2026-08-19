<?php

namespace App\Http\Controllers\Utilities;

use App\Enums\Utility;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ApiGatewayController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorizeUtility(Utility::ApiGateway);

        return view('utilities.api-gateway');
    }
}
