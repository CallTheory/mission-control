<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __construct()
    {
        $settings = Settings::first();

        if ($settings && isset($settings->api_whitelist) && $settings->api_whitelist) {
            $this->middleware('api_whitelist');
        }

    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
