<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use App\Services\Mcp\McpServer;
use App\Services\Mcp\Protocol\JsonRpcMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * MCP Streamable HTTP Transport Controller
 *
 * Implements MCP protocol version 2025-03-26 (Streamable HTTP)
 * - POST returns JSON responses directly (no SSE overhead)
 * - GET returns 405 (server-initiated messages not supported)
 *
 * @see https://modelcontextprotocol.io/specification/2025-03-26/basic/transports
 */
class McpSseController extends Controller
{
    private McpServer $mcpServer;

    public function __construct()
    {
        $this->mcpServer = new McpServer;
    }

    /**
     * MCP protocol endpoint - Streamable HTTP transport
     *
     * POST: JSON-RPC request → JSON response
     * GET: 405 Method Not Allowed (server-initiated messages not supported)
     */
    public function protocol(Request $request): JsonResponse
    {
        // Check if MCP is enabled
        $settings = Settings::first();
        if (! $settings || ! $settings->mcp_enabled) {
            return response()->json([
                'error' => 'MCP server is disabled',
            ], 503);
        }

        // Apply rate limiting
        $user = $request->user();
        $key = 'mcp:'.($user ? $user->id : $request->ip());
        $limit = $settings->mcp_rate_limit ?? 100;

        if (! RateLimiter::attempt($key, $limit, function () {}, 60)) {
            return response()->json([
                'error' => 'Rate limit exceeded',
            ], 429);
        }

        // Apply CORS if configured
        $headers = [];
        if ($settings->mcp_cors_origins) {
            $origins = json_decode($settings->mcp_cors_origins, true);
            $origin = $request->header('Origin');
            if ($origin && in_array($origin, $origins)) {
                $headers['Access-Control-Allow-Origin'] = $origin;
                $headers['Access-Control-Allow-Methods'] = 'POST, OPTIONS';
                $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization';
            }
        }

        // GET requests return 405 - server-initiated messages not supported
        if ($request->isMethod('GET')) {
            return response()->json([
                'error' => 'Method not allowed',
                'message' => 'Server-initiated messages are not supported. Use POST for JSON-RPC requests.',
            ], 405)->withHeaders($headers);
        }

        // Handle POST with JSON-RPC payload
        return $this->handleJsonRpcPost($request, $headers);
    }

    /**
     * Handle POST requests with JSON-RPC payload
     */
    private function handleJsonRpcPost(Request $request, array $corsHeaders = []): JsonResponse
    {
        $settings = Settings::first();
        $user = $request->user();
        Auth::setUser($user);

        // Check team context if required
        if ($settings->mcp_require_team_context && ! $user->current_team_id) {
            return response()->json(
                json_decode(JsonRpcMessage::error(
                    -32603,
                    'Team context required',
                    'User must have a current team selected'
                )->toJson(), true)
            )->withHeaders($corsHeaders);
        }

        $payload = $request->getContent();
        $message = JsonRpcMessage::parse($payload);

        if (! $message) {
            return response()->json(
                json_decode(JsonRpcMessage::error(
                    -32700,
                    'Parse error',
                    'Invalid JSON'
                )->toJson(), true)
            )->withHeaders($corsHeaders);
        }

        // Log if enabled
        if ($settings->mcp_logging_enabled) {
            $logLevel = $settings->mcp_log_level ?? 'info';
            Log::$logLevel('[MCP Request]', [
                'user' => $user->email,
                'method' => $message->method,
                'params' => $message->params,
            ]);
        }

        // Set execution timeout
        $timeout = $settings->mcp_timeout ?? 30;
        set_time_limit($timeout);

        $response = $this->mcpServer->handleMessage($message);

        if ($response) {
            $responseJson = $response->toJson();
            $maxSize = $settings->mcp_max_response_size ?? 1048576;

            // Check response size
            if (strlen($responseJson) > $maxSize) {
                return response()->json(
                    json_decode(JsonRpcMessage::error(
                        -32603,
                        'Response too large',
                        'Response exceeds maximum allowed size'
                    )->toJson(), true)
                )->withHeaders($corsHeaders);
            }

            // Log response if enabled
            if ($settings->mcp_logging_enabled) {
                $logLevel = $settings->mcp_log_level ?? 'info';
                Log::$logLevel('[MCP Response]', [
                    'user' => $user->email,
                    'response_size' => strlen($responseJson),
                ]);
            }

            return response()->json(json_decode($responseJson, true))->withHeaders($corsHeaders);
        }

        // No response for notifications (204 No Content)
        return response()->json(null, 204)->withHeaders($corsHeaders);
    }
}
