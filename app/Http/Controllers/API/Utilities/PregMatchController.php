<?php

namespace App\Http\Controllers\API\Utilities;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PregMatchController extends Controller
{
    public function __construct(){
        $settings = Settings::firstOrFail();

        if($settings->api_whitelist){
            $this->middleware('api_whitelist');
        }

        if($settings->require_api_tokens){
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
            'string' => 'required|string|min:3',
            'pattern' => 'required|string',
        ], [
            'string' => 'The `string` field is required and must be 3 characters or longer',
            'pattern' => 'The `pattern` field is required and must be a valid regular expression',
        ]);

        if ($validator->fails()) {
            abort(400, App::environment('local') ? $validator->messages()->first() : 'Failed validation of input values.');
        }

        return response()->json( $this->preg_match($string, $regex,), 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function preg_match( string $string, string $regex,): array
    {
        $matches = [];
        if(@preg_match_all($regex, $string, $matches, PREG_PATTERN_ORDER) === false){
            return [];
        }

        if(count($matches[0])){
            return $matches[0];
        }

        return [];

    }
}
