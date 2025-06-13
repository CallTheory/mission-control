<?php

namespace App\Http\Controllers\API\Utilities;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TextBetweenController extends Controller
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

    public function __invoke(Request $request): JsonResponse
    {
        $string = $request->input('string') ?? null;
        $start = $request->input('start') ?? null;
        $end = $request->input('end') ?? null;

        $validator = Validator::make([
            'string' => $string,
            'start' => $start,
            'end' => $end,
        ], [
            'string' => 'required|string|min:3',
            'start' => 'required|string|min:1',
            'end' => 'required|string|min:1',
        ], [
            'string' => 'The `string` field is required and must be 3 characters or longer',
            'start' => 'The `start` field is required and must be 1 characters or longer',
            'end' => 'The `end` field is required and must be 1 characters or longer',
        ]);

        if ($validator->fails()) {
            abort(400, App::environment('local') ? $validator->messages()->first() : 'Failed validation of `string` values.');
        }

        return response()->json($this->string_between($string, $start, $end), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function string_between(string $string, string $start, string $end): string
    {
        return Str::betweenFirst($string, $start, $end);
    }
}
