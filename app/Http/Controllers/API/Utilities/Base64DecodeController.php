<?php

namespace App\Http\Controllers\API\Utilities;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Base64DecodeController extends Controller
{
    public function __construct()
    {
        $settings = Settings::firstOrFail();

        if ($settings->api_whitelist) {
            $this->middleware('api_whitelist');
        }

        if ($settings->require_api_tokens) {
            $this->middleware('auth:sanctum');
        }

    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $string = $request->input('string') ?? null;

        if ($string === null) {
            abort(400, 'Missing `string` parameter (GET or POST)');
        }

        $validator = Validator::make([
            'string' => $string,
        ], [
            'string' => 'required|string|min:2',
        ], [
            'string' => 'The `string` field is required and must be 2 characters or longer',
        ]);

        if ($validator->fails()) {
            abort(400, App::environment('local') ? $validator->messages()->first() : 'Failed validation of `string` values.');
        }

        return response()->json($this->base64_decode($string), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function base64_decode(string $string): string
    {
        return Str::fromBase64($string);
    }
}
