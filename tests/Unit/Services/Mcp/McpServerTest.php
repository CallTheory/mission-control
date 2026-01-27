<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp;

use App\Models\System\Settings;
use App\Services\Mcp\McpServer;
use App\Services\Mcp\Protocol\JsonRpcMessage;
use App\Services\Mcp\Tools\ToolInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private McpServer $server;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a settings record with all tools enabled (including the default tools)
        Settings::create([
            'mcp_allowed_tools' => json_encode(['get_vcon_record', 'get_call_recording', 'test_tool', 'failing_tool']),
        ]);

        $this->server = new McpServer;
    }

    public function test_handles_initialize_request(): void
    {
        $request = JsonRpcMessage::request('initialize', [
            'protocolVersion' => '2025-03-26',
            'capabilities' => [],
        ], 1);

        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertIsArray($response->result);
        $this->assertEquals('2025-03-26', $response->result['protocolVersion']);
        $this->assertArrayHasKey('capabilities', $response->result);
        $this->assertArrayHasKey('serverInfo', $response->result);
        $this->assertEquals('mission-control-mcp', $response->result['serverInfo']['name']);
    }

    public function test_handles_initialized_notification(): void
    {
        $request = JsonRpcMessage::notification('initialized');
        $response = $this->server->handleMessage($request);

        $this->assertNull($response);
    }

    public function test_handles_tools_list_request(): void
    {
        $request = JsonRpcMessage::request('tools/list', null, 2);
        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertIsArray($response->result);
        $this->assertArrayHasKey('tools', $response->result);
        $this->assertIsArray($response->result['tools']);

        // Should have at least the vCon tool
        $this->assertGreaterThan(0, count($response->result['tools']));

        $tool = $response->result['tools'][0];
        $this->assertArrayHasKey('name', $tool);
        $this->assertArrayHasKey('description', $tool);
        $this->assertArrayHasKey('inputSchema', $tool);
    }

    public function test_handles_ping_request(): void
    {
        $request = JsonRpcMessage::request('ping', null, 3);
        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertIsArray($response->result);
        $this->assertTrue($response->result['pong']);
    }

    public function test_handles_unknown_method(): void
    {
        $request = JsonRpcMessage::request('unknown_method', null, 4);
        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertNotNull($response->error);
        $this->assertEquals(-32601, $response->error['code']);
        $this->assertEquals('Method not found', $response->error['message']);
    }

    public function test_handles_tool_call_with_invalid_params(): void
    {
        $request = JsonRpcMessage::request('tools/call', [], 5);
        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertNotNull($response->error);
        $this->assertEquals(-32602, $response->error['code']);
        $this->assertEquals('Invalid params', $response->error['message']);
    }

    public function test_handles_tool_call_with_unknown_tool(): void
    {
        $request = JsonRpcMessage::request('tools/call', [
            'name' => 'unknown_tool',
            'arguments' => [],
        ], 6);

        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertNotNull($response->error);
        $this->assertEquals(-32602, $response->error['code']);
        $this->assertStringContainsString('unknown_tool', $response->error['data']);
    }

    public function test_registers_and_executes_custom_tool(): void
    {
        // Create a mock tool
        $mockTool = new class implements ToolInterface
        {
            public function getName(): string
            {
                return 'test_tool';
            }

            public function getDescription(): string
            {
                return 'A test tool';
            }

            public function getInputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'input' => ['type' => 'string'],
                    ],
                ];
            }

            public function execute(array $arguments): mixed
            {
                return ['output' => 'test result: '.($arguments['input'] ?? '')];
            }
        };

        $this->server->registerTool($mockTool);

        // Test that tool appears in list
        $listRequest = JsonRpcMessage::request('tools/list', null, 7);
        $listResponse = $this->server->handleMessage($listRequest);

        $toolNames = array_column($listResponse->result['tools'], 'name');
        $this->assertContains('test_tool', $toolNames);

        // Test tool execution
        $callRequest = JsonRpcMessage::request('tools/call', [
            'name' => 'test_tool',
            'arguments' => ['input' => 'hello'],
        ], 8);

        $callResponse = $this->server->handleMessage($callRequest);

        $this->assertNotNull($callResponse);
        $this->assertNotNull($callResponse->result);
        $this->assertArrayHasKey('content', $callResponse->result);
        $this->assertIsArray($callResponse->result['content']);

        $content = $callResponse->result['content'][0];
        $this->assertEquals('text', $content['type']);
        $this->assertStringContainsString('test result: hello', $content['text']);
    }

    public function test_handles_non_request_messages(): void
    {
        $response = JsonRpcMessage::success(['data' => 'test'], 9);
        $result = $this->server->handleMessage($response);

        $this->assertNull($result);
    }

    public function test_handles_tool_execution_exception(): void
    {
        // Create a mock tool that throws an exception
        $mockTool = new class implements ToolInterface
        {
            public function getName(): string
            {
                return 'failing_tool';
            }

            public function getDescription(): string
            {
                return 'A tool that fails';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function execute(array $arguments): mixed
            {
                throw new \RuntimeException('Tool execution failed');
            }
        };

        $this->server->registerTool($mockTool);

        $request = JsonRpcMessage::request('tools/call', [
            'name' => 'failing_tool',
            'arguments' => [],
        ], 10);

        $response = $this->server->handleMessage($request);

        $this->assertNotNull($response);
        $this->assertNotNull($response->error);
        $this->assertEquals(-32603, $response->error['code']);
        $this->assertEquals('Tool execution failed', $response->error['message']);
        $this->assertStringContainsString('Tool execution failed', $response->error['data']);
    }
}
