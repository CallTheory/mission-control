<?php

declare(strict_types=1);

namespace App\Services\Mcp;

use App\Models\System\Settings;
use App\Services\Mcp\Protocol\JsonRpcMessage;
use App\Services\Mcp\Tools\ToolInterface;
use App\Services\Mcp\Tools\VConTool;

class McpServer
{
    private array $tools = [];
    
    public function __construct()
    {
        $this->registerDefaultTools();
    }
    
    /**
     * Register default MCP tools
     */
    private function registerDefaultTools(): void
    {
        $this->registerTool(new VConTool());
    }
    
    /**
     * Register a tool
     */
    public function registerTool(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }
    
    /**
     * Handle a JSON-RPC message
     */
    public function handleMessage(JsonRpcMessage $message): ?JsonRpcMessage
    {
        if (!$message->isRequest()) {
            return null;
        }
        
        try {
            switch ($message->method) {
                case 'initialize':
                    return $this->handleInitialize($message);
                    
                case 'initialized':
                    // Client confirmation, no response needed
                    return null;
                    
                case 'tools/list':
                    return $this->handleToolsList($message);
                    
                case 'tools/call':
                    return $this->handleToolCall($message);
                    
                case 'ping':
                    return JsonRpcMessage::success(['pong' => true], $message->id);
                    
                default:
                    return JsonRpcMessage::error(
                        -32601,
                        'Method not found',
                        null,
                        $message->id
                    );
            }
        } catch (\Exception $e) {
            return JsonRpcMessage::error(
                -32603,
                'Internal error',
                $e->getMessage(),
                $message->id
            );
        }
    }
    
    /**
     * Handle initialize request
     */
    private function handleInitialize(JsonRpcMessage $message): JsonRpcMessage
    {
        return JsonRpcMessage::success([
            'protocolVersion' => '2024-11-05',
            'capabilities' => [
                'tools' => [
                    'listChanged' => false
                ],
                'resources' => [
                    'subscribe' => false,
                    'listChanged' => false
                ],
                'prompts' => [
                    'listChanged' => false
                ],
                'logging' => []
            ],
            'serverInfo' => [
                'name' => 'mission-control-mcp',
                'version' => '1.0.0'
            ]
        ], $message->id);
    }
    
    /**
     * Handle tools/list request
     */
    private function handleToolsList(JsonRpcMessage $message): JsonRpcMessage
    {
        $tools = [];
        $settings = Settings::first();
        $allowedTools = $settings && $settings->mcp_allowed_tools 
            ? json_decode($settings->mcp_allowed_tools, true) 
            : [];
        
        foreach ($this->tools as $tool) {
            // Only list tools that are enabled in settings
            if (!empty($allowedTools) && !in_array($tool->getName(), $allowedTools)) {
                continue;
            }
            
            $tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema()
            ];
        }
        
        return JsonRpcMessage::success([
            'tools' => $tools
        ], $message->id);
    }
    
    /**
     * Handle tools/call request
     */
    private function handleToolCall(JsonRpcMessage $message): JsonRpcMessage
    {
        $params = $message->params;
        
        if (!is_array($params) || !isset($params['name'])) {
            return JsonRpcMessage::error(
                -32602,
                'Invalid params',
                'Tool name is required',
                $message->id
            );
        }
        
        $toolName = $params['name'];
        $arguments = $params['arguments'] ?? [];
        
        // Check if tool exists
        if (!isset($this->tools[$toolName])) {
            return JsonRpcMessage::error(
                -32602,
                'Invalid params',
                "Tool '{$toolName}' not found",
                $message->id
            );
        }
        
        // Check if tool is allowed
        $settings = Settings::first();
        $allowedTools = $settings && $settings->mcp_allowed_tools 
            ? json_decode($settings->mcp_allowed_tools, true) 
            : [];
        
        if (!empty($allowedTools) && !in_array($toolName, $allowedTools)) {
            return JsonRpcMessage::error(
                -32602,
                'Invalid params',
                "Tool '{$toolName}' is not enabled",
                $message->id
            );
        }
        
        try {
            $tool = $this->tools[$toolName];
            $result = $tool->execute($arguments);
            
            return JsonRpcMessage::success([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($result, JSON_PRETTY_PRINT)
                    ]
                ]
            ], $message->id);
        } catch (\Exception $e) {
            return JsonRpcMessage::error(
                -32603,
                'Tool execution failed',
                $e->getMessage(),
                $message->id
            );
        }
    }
}