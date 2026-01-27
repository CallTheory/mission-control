<?php

use App\Http\Controllers\API\Agents\CallerHistoryController as CallerHistory;
use App\Http\Controllers\API\Agents\InboundEmail\ForwardController as ForwardInboundEmail;
use App\Http\Controllers\API\Agents\InboundEmail\ViewController as ViewInboundEmail;
use App\Http\Controllers\API\Integrations\StripeFillOutController as StripeFillOut;
use App\Http\Controllers\Api\McpSseController;
use App\Http\Controllers\API\MeController;
use App\Http\Controllers\API\Utilities\ApaCaseController;
use App\Http\Controllers\API\Utilities\Base64DecodeController;
use App\Http\Controllers\API\Utilities\Base64EncodeController;
use App\Http\Controllers\API\Utilities\CamelCaseController;
use App\Http\Controllers\API\Utilities\IsJsonController;
use App\Http\Controllers\API\Utilities\IsUrlController;
use App\Http\Controllers\API\Utilities\KebabCaseController;
use App\Http\Controllers\API\Utilities\PregMatchController;
use App\Http\Controllers\API\Utilities\SnakeCaseController;
use App\Http\Controllers\API\Utilities\StudlyCaseController;
use App\Http\Controllers\API\Utilities\TextBetweenController;
use App\Http\Controllers\API\Utilities\TitleCaseController;
use App\Http\Controllers\API\Utilities\TransliterateAsciiController;
use App\Models\Stats\Helpers;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

if (Helpers::isSystemFeatureEnabled('api-gateway')) {

    Route::match(['get', 'post'], '/utilities/kebab-case', KebabCaseController::class)
        ->name('api.utilities.kebab-case');

    Route::match(['get', 'post'], '/utilities/transliterate', TransliterateAsciiController::class)
        ->name('api.utilities.transliterate');

    Route::match(['get', 'post'], '/utilities/text-between', TextBetweenController::class)
        ->name('api.utilities.text-between');

    Route::match(['get', 'post'], '/utilities/preg-match', PregMatchController::class)
        ->name('api.utilities.preg-match');

    Route::match(['get', 'post'], '/utilities/is/json', IsJsonController::class)
        ->name('api.utilities.is.json');

    Route::match(['get', 'post'], '/utilities/is/url', IsUrlController::class)
        ->name('api.utilities.is.url');

    Route::match(['get', 'post'], '/utilities/apa-title-case', ApaCaseController::class)
        ->name('api.utilities.apa-title-case');

    Route::match(['get', 'post'], '/utilities/title-case', TitleCaseController::class)
        ->name('api.utilities.title-case');

    Route::match(['get', 'post'], '/utilities/base64-encode', Base64EncodeController::class)
        ->name('api.utilities.base64-encode');

    Route::match(['get', 'post'], '/utilities/base64-decode', Base64DecodeController::class)
        ->name('api.utilities.base64-decode');

    Route::match(['get', 'post'], '/utilities/camel-case', CamelCaseController::class)
        ->name('api.utilities.camel-case');

    Route::match(['get', 'post'], '/utilities/snake-case', SnakeCaseController::class)
        ->name('api.utilities.snake-case');

    Route::match(['get', 'post'], '/utilities/studly-case', StudlyCaseController::class)
        ->name('api.utilities.studly-case');

    // Stripe New Customer Form
    Route::get('/integrations/stripe-fillout', StripeFillOut::class)
        ->name('api.integrations.stripe-fillout');

    // Basic "me" api with auth
    Route::get('/me', MeController::class)
        ->name('api.me')
        ->middleware('auth:sanctum');

    // Requires an API key from the application, also takes ani as POST param
    Route::post('/agents/recent-caller/{clientNumber}', CallerHistory::class)
        ->name('api.agents.recent-caller')
        ->middleware('auth:sanctum');

    // MCP Streamable HTTP Transport (protocol version 2025-03-26)
    // POST: JSON-RPC requests, GET: 405 (server-initiated messages not supported)
    Route::prefix('mcp')->middleware('auth:sanctum')->group(function () {
        Route::match(['get', 'post'], '/protocol', [McpSseController::class, 'protocol'])
            ->name('api.mcp.protocol');
    });
}

if (Helpers::isSystemFeatureEnabled('inbound-email')) {

    // Requires valid signed url generated from inbound email controllers
    Route::get('/agents/inbound-email/view/{email}', ViewInboundEmail::class)
        ->name('api.agents.inbound-email.view')
        ->middleware('signed');

    // Protected by a system-generated API key available in an email section
    Route::post('/agents/inbound-email/forward/{email}', ForwardInboundEmail::class)
        ->name('api.agents.inbound-email.forward');
}
