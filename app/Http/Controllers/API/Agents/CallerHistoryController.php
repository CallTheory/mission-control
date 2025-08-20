<?php

namespace App\Http\Controllers\API\Agents;

use App\Http\Controllers\Controller;
use App\Models\API\RecentCaller;
use App\Models\System\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CallerHistoryController extends Controller
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

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, int $clientNumber): JsonResponse
    {
        // allows us to use a formatting phone field to look up instead of unformatted 10-digit ANI only
        $ani = Str::replace(['-', '(', ')', '.', '+', ' '], '', $request->input('ANI'));

        $validator = Validator::make([
            'ClientNumber' => $clientNumber,
            'ANI' => $ani,
            'ResultCount' => $request->input('ResultCount'),
        ], [
            'ClientNumber' => 'required|integer',
            'ANI' => 'required|string|size:10',
            'ResultCount' => 'nullable|integer|min_digits:1|max_digits:10',
        ], [
            'ANI' => 'The ANI is required',
            'ClientNumber' => 'The ClientNumber is required',
        ]);

        if ($validator->fails()) {
            abort(400, App::environment('local') ? $validator->messages()->first() : 'Failed validation of ClientNumber, ANI, or ResultCount values. Check your syntax.');
        }

        $recent = new RecentCaller($request->input('ANI'), $clientNumber);
        $recent_calls = $recent->recent($request->input('ResultCount'));

        if (! count($recent_calls) > 0) {
            abort(404, 'No recent calls found');
        }

        $deduplicated = $this->deduplicate_array($recent_calls, 'FROM_TXT');

        return response()->json($deduplicated, 200, [], JSON_OBJECT_AS_ARRAY | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    protected function deduplicate_array($array, $key): array
    {
        $result = [];
        $seen = [];

        foreach ($array as $item) {
            if (isset($item[$key])) {
                $value = $item[$key];
                if (! isset($seen[$value])) {
                    $seen[$value] = true;
                    $result[$key] = $item;
                }
            }
        }

        return $result;
    }
}
