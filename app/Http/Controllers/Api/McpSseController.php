<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\System\Settings;
use App\Services\Mcp\McpServer;
use App\Services\Mcp\Protocol\JsonRpcMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class McpSseController extends Controller
{
    private McpServer $mcpServer;
    
    public function __construct()
    {
        $this->mcpServer = new McpServer();
    }
    
    /**
     * MCP protocol SSE endpoint with JSON-RPC support
     */
    public function protocol(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        // Check if MCP is enabled
        $settings = Settings::first();
        if (!$settings || !$settings->mcp_enabled) {
            return response()->json([
                'error' => 'MCP server is disabled'
            ], 503);
        }
        
        // Apply rate limiting
        $user = $request->user();
        $key = 'mcp:' . ($user ? $user->id : $request->ip());
        $limit = $settings->mcp_rate_limit ?? 100;
        
        if (!RateLimiter::attempt($key, $limit, function() {}, 60)) {
            return response()->json([
                'error' => 'Rate limit exceeded'
            ], 429);
        }
        
        // Apply CORS if configured
        if ($settings->mcp_cors_origins) {
            $origins = json_decode($settings->mcp_cors_origins, true);
            $origin = $request->header('Origin');
            if ($origin && in_array($origin, $origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
            }
        }
        // For POST requests with JSON-RPC payload
        if ($request->isMethod('POST') && $request->header('Content-Type') === 'application/json') {
            return $this->handleJsonRpcPost($request);
        }
        
        // For SSE connections
        return response()->stream(function () use ($request) {
            $user = $request->user();
            Auth::setUser($user); // Ensure auth context for tools
            
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            
            // Send initial connection event
            $this->sendEventJson('message', JsonRpcMessage::notification('connection_established', [
                'user' => $user->name,
                'team' => $user->currentTeam->name ?? 'No team',
                'timestamp' => now()->toIso8601String()
            ])->toJson());
            
            // For SSE, we can only send events, not receive them
            // Clients should use POST requests for sending JSON-RPC messages
            $lastPing = time();
            
            while (true) {
                if (connection_aborted()) {
                    break;
                }
                
                // Send heartbeat every 30 seconds
                if (time() - $lastPing >= 30) {
                    $this->sendEventJson('message', JsonRpcMessage::notification('heartbeat', [
                        'timestamp' => now()->toIso8601String()
                    ])->toJson());
                    $lastPing = time();
                }
                
                usleep(1000000); // Sleep for 1 second
            }
            
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
    
    /**
     * Handle POST requests with JSON-RPC payload
     */
    private function handleJsonRpcPost(Request $request): \Illuminate\Http\JsonResponse
    {
        $settings = Settings::first();
        $user = $request->user();
        Auth::setUser($user);
        
        // Check team context if required
        if ($settings->mcp_require_team_context && !$user->current_team_id) {
            return response()->json(JsonRpcMessage::error(
                -32603,
                'Team context required',
                'User must have a current team selected'
            )->toJson());
        }
        
        $payload = $request->getContent();
        $message = JsonRpcMessage::parse($payload);
        
        if (!$message) {
            $error = JsonRpcMessage::error(
                -32700,
                'Parse error',
                'Invalid JSON'
            );
            return response()->json(json_decode($error->toJson(), true));
        }
        
        // Log if enabled
        if ($settings->mcp_logging_enabled) {
            $logLevel = $settings->mcp_log_level ?? 'info';
            Log::$logLevel('[MCP Request]', [
                'user' => $user->email,
                'method' => $message->method,
                'params' => $message->params
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
                return response()->json(JsonRpcMessage::error(
                    -32603,
                    'Response too large',
                    'Response exceeds maximum allowed size'
                )->toJson());
            }
            
            // Log response if enabled
            if ($settings->mcp_logging_enabled) {
                $logLevel = $settings->mcp_log_level ?? 'info';
                Log::$logLevel('[MCP Response]', [
                    'user' => $user->email,
                    'response_size' => strlen($responseJson)
                ]);
            }
            
            return response()->json(json_decode($responseJson, true));
        }
        
        // No response for notifications
        return response()->json(null, 204);
    }
    
    /**
     * Process incoming JSON-RPC message
     */
    private function processJsonRpcMessage(string $line): void
    {
        $message = JsonRpcMessage::parse($line);
        
        if (!$message) {
            $this->sendEventJson('message', JsonRpcMessage::error(
                -32700,
                'Parse error',
                'Invalid JSON'
            )->toJson());
            return;
        }
        
        $response = $this->mcpServer->handleMessage($message);
        
        if ($response) {
            $this->sendEventJson('message', $response->toJson());
        }
    }
    
    /**
     * User information SSE endpoint (legacy)
     */
    public function userInfo(Request $request): StreamedResponse
    {
        return response()->stream(function () use ($request) {
            $user = $request->user();
            
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            
            // Send initial user info
            $this->sendEvent('user', [
                'name' => $user->name,
                'email' => $user->email,
                'team' => $user->currentTeam->name ?? 'No team',
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Send connection confirmation
            $this->sendEvent('connected', [
                'message' => 'Connection established',
                'server' => 'user-info'
            ]);
            
            // Keep connection alive with periodic heartbeats
            $lastPing = time();
            while (true) {
                if (connection_aborted()) {
                    break;
                }
                
                // Send heartbeat every 30 seconds
                if (time() - $lastPing >= 30) {
                    $this->sendEvent('heartbeat', ['timestamp' => now()->toIso8601String()]);
                    $lastPing = time();
                }
                
                usleep(1000000); // Sleep for 1 second
            }
            
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
    
    /**
     * Generic MCP SSE endpoint for custom implementations
     */
    public function custom(Request $request, string $type): StreamedResponse
    {
        return response()->stream(function () use ($request, $type) {
            $user = $request->user();
            
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no');
            
            // Send connection info
            $this->sendEvent('connected', [
                'message' => 'Connection established',
                'server' => $type,
                'user' => $user->name
            ]);
            
            // Handle different MCP server types
            switch ($type) {
                case 'notifications':
                    // Example: Stream user notifications
                    $this->sendEvent('notification_count', [
                        'unread' => $user->unreadNotifications()->count()
                    ]);
                    break;
                    
                case 'metrics':
                    // Example: Stream team metrics
                    $this->sendEvent('team_metrics', [
                        'team' => $user->currentTeam->name,
                        'members' => $user->currentTeam->users()->count()
                    ]);
                    break;
                    
                default:
                    $this->sendEvent('error', [
                        'message' => 'Unknown MCP server type: ' . $type
                    ]);
            }
            
            // Keep alive loop
            $lastPing = time();
            while (true) {
                if (connection_aborted()) {
                    break;
                }
                
                if (time() - $lastPing >= 30) {
                    $this->sendEvent('heartbeat', [
                        'timestamp' => now()->toIso8601String(),
                        'server' => $type
                    ]);
                    $lastPing = time();
                }
                
                usleep(1000000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
    
    /**
     * Send an SSE event
     */
    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Send an SSE event with JSON string data
     */
    private function sendEventJson(string $event, string $jsonData): void
    {
        echo "event: {$event}\n";
        echo "data: " . $jsonData . "\n\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }
}