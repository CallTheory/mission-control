<?php

namespace App\Http\Controllers\API\Utilities;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;

class PregMatchController extends Controller
{
    public function __construct()
    {
        $settings = Settings::first();

        if ($settings && isset($settings->api_whitelist) && $settings->api_whitelist) {
            $this->middleware('api_whitelist');
        }

        if ($settings && isset($settings->require_api_tokens) && $settings->require_api_tokens) {
            $this->middleware('auth:sanctum');
        }
    }

    public function __invoke(Request $request): JsonResponse
    {
        $string = $request->input('string') ?? null;
        $regex = $request->input('pattern') ?? null;

        $validator = Validator::make([
            'string' => $string,
            'pattern' => $regex,
        ], [
            // Bound the input sizes so worst-case matching work stays limited.
            'string' => 'required|string|min:3|max:4096',
            'pattern' => 'required|string|max:512',
        ], [
            'string' => 'The `string` field is required and must be between 3 and 4096 characters',
            'pattern' => 'The `pattern` field is required, must be a valid regular expression, and at most 512 characters',
        ]);

        if ($validator->fails()) {
            abort(400, App::environment('local') ? $validator->messages()->first() : 'Failed validation of input values.');
        }

        return response()->json($this->preg_match($string, $regex), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function preg_match(string $string, string $regex): array
    {
        $originalBacktrack = ini_get('pcre.backtrack_limit');
        $originalRecursion = ini_get('pcre.recursion_limit');

        // Bound catastrophic backtracking on an attacker-supplied pattern: once the
        // limit is hit, preg_match_all returns false (handled below) instead of
        // pinning a worker. Restored afterward so the low limit is request-scoped.
        ini_set('pcre.backtrack_limit', '50000');
        ini_set('pcre.recursion_limit', '50000');

        try {
            $matches = [];
            if (@preg_match_all($regex, $string, $matches, PREG_PATTERN_ORDER) === false) {
                return [];
            }

            return count($matches[0]) ? $matches[0] : [];
        } finally {
            ini_set('pcre.backtrack_limit', $originalBacktrack);
            ini_set('pcre.recursion_limit', $originalRecursion);
        }
    }
}
