<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\McpSseController;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpSseControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Register MCP routes directly (bypasses api-gateway feature flag check at boot time)
        Route::middleware(['api', 'auth:sanctum'])->prefix('api/mcp')->group(function () {
            Route::match(['get', 'post'], '/protocol', [McpSseController::class, 'protocol'])
                ->name('api.mcp.protocol');
        });

        // Create settings with MCP enabled
        Settings::create([
            'mcp_enabled' => true,
            'mcp_rate_limit' => 100,
            'mcp_timeout' => 30,
            'mcp_logging_enabled' => false,
            'mcp_log_level' => 'error',
            'mcp_max_response_size' => 1048576,
            'mcp_require_team_context' => false, // Disable for easier testing
            'mcp_allowed_tools' => ['get_vcon_record'], // Enable vcon tool
        ]);

        // Create a user with a team
        $this->user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $this->user->id]);
        $this->user->current_team_id = $team->id;
        $this->user->save();

        // Create API token
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_protocol_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);

        // Sanctum returns 400/401 for unauthenticated API routes
        $response->assertStatus(400);
    }

    public function test_protocol_get_returns_405_method_not_allowed(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->get('/api/mcp/protocol');

        $response->assertStatus(405)
            ->assertJson([
                'error' => 'Method not allowed',
                'message' => 'Server-initiated messages are not supported. Use POST for JSON-RPC requests.',
            ]);
    }

    public function test_protocol_endpoint_handles_initialize_request(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => [],
            ],
            'id' => 1,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'result' => [
                    'protocolVersion' => '2025-03-26',
                    'capabilities' => [
                        'tools' => ['listChanged' => false],
                        'resources' => ['subscribe' => false, 'listChanged' => false],
                        'prompts' => ['listChanged' => false],
                        'logging' => [],
                    ],
                    'serverInfo' => [
                        'name' => 'mission-control-mcp',
                        'version' => '1.0.0',
                    ],
                ],
                'id' => 1,
            ]);
    }

    public function test_protocol_endpoint_handles_tools_list_request(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 2,
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertArrayHasKey('result', $data);
        $this->assertArrayHasKey('tools', $data['result']);
        $this->assertIsArray($data['result']['tools']);

        // Check for vCon tool
        $tools = $data['result']['tools'];
        $this->assertGreaterThan(0, count($tools));

        $vconTool = null;
        foreach ($tools as $tool) {
            if ($tool['name'] === 'get_vcon_record') {
                $vconTool = $tool;
                break;
            }
        }

        $this->assertNotNull($vconTool);
        $this->assertArrayHasKey('description', $vconTool);
        $this->assertArrayHasKey('inputSchema', $vconTool);
    }

    public function test_protocol_endpoint_handles_ping_request(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 3,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'result' => ['pong' => true],
                'id' => 3,
            ]);
    }

    public function test_protocol_endpoint_handles_invalid_json(): void
    {
        Sanctum::actingAs($this->user);

        // Send raw invalid JSON content
        $response = $this->call('POST', '/api/mcp/protocol', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $response->assertStatus(200);

        // The response should be JSON-RPC error format
        $response->assertJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error',
                'data' => 'Invalid JSON',
            ],
        ]);
    }

    public function test_protocol_endpoint_handles_unknown_method(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'unknown_method',
            'id' => 4,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found',
                ],
                'id' => 4,
            ]);
    }

    public function test_protocol_endpoint_handles_tool_call_without_name(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [],
            'id' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params',
                    'data' => 'Tool name is required',
                ],
                'id' => 5,
            ]);
    }

    public function test_protocol_endpoint_handles_tool_call_with_unknown_tool(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'non_existent_tool',
                'arguments' => [],
            ],
            'id' => 6,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params',
                    'data' => "Tool 'non_existent_tool' not found",
                ],
                'id' => 6,
            ]);
    }

    public function test_protocol_endpoint_handles_notification(): void
    {
        Sanctum::actingAs($this->user);

        // Notifications don't have an id and shouldn't return a response
        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
            // No id field for notifications
        ]);

        $response->assertStatus(204); // No content for notifications
    }

    public function test_protocol_endpoint_with_bearer_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->token,
            'Content-Type' => 'application/json',
        ])->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 8,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'result' => ['pong' => true],
                'id' => 8,
            ]);
    }

    public function test_protocol_endpoint_handles_tool_call_missing_required_param(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/mcp/protocol', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'get_vcon_record',
                'arguments' => [], // Missing required callId
            ],
            'id' => 9,
        ]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals(-32603, $data['error']['code']);
        $this->assertStringContainsString('callId is required', $data['error']['data']);
    }

    protected function tearDown(): void
    {
        // Reset execution time limit since the MCP controller calls set_time_limit()
        set_time_limit(0);
        \Mockery::close();
        parent::tearDown();
    }
}
