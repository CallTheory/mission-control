<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Models\System\Settings;
use App\Services\Mcp\McpServer as McpServerService;
use Illuminate\View\View;
use Livewire\Component;

class McpServer extends Component
{
    public bool $mcp_enabled = false;
    public int $mcp_rate_limit = 100;
    public int $mcp_timeout = 30;
    public array $mcp_allowed_tools = [];
    public bool $mcp_logging_enabled = false;
    public string $mcp_log_level = 'error';
    public int $mcp_max_response_size = 1048576;
    public bool $mcp_require_team_context = true;
    public string $mcp_cors_origins = '';
    
    // Available tools from the MCP server
    public array $available_tools = [];
    
    protected $rules = [
        'mcp_enabled' => 'boolean',
        'mcp_rate_limit' => 'integer|min:1|max:1000',
        'mcp_timeout' => 'integer|min:1|max:300',
        'mcp_allowed_tools' => 'array',
        'mcp_logging_enabled' => 'boolean',
        'mcp_log_level' => 'in:error,warning,info,debug',
        'mcp_max_response_size' => 'integer|min:1024|max:10485760', // 1KB to 10MB
        'mcp_require_team_context' => 'boolean',
        'mcp_cors_origins' => 'nullable|string',
    ];
    
    public function mount(): void
    {
        $settings = Settings::first();
        
        $this->mcp_enabled = $settings->mcp_enabled ?? false;
        $this->mcp_rate_limit = $settings->mcp_rate_limit ?? 100;
        $this->mcp_timeout = $settings->mcp_timeout ?? 30;
        $this->mcp_allowed_tools = $settings->mcp_allowed_tools ?: [];
        $this->mcp_logging_enabled = $settings->mcp_logging_enabled ?? false;
        $this->mcp_log_level = $settings->mcp_log_level ?? 'error';
        $this->mcp_max_response_size = $settings->mcp_max_response_size ?? 1048576;
        $this->mcp_require_team_context = $settings->mcp_require_team_context ?? true;
        $this->mcp_cors_origins = $settings->mcp_cors_origins ? implode("\n", $settings->mcp_cors_origins) : '';
        
        // Get available tools from the MCP server
        $mcpServer = new McpServerService();
        $reflection = new \ReflectionClass($mcpServer);
        $toolsProperty = $reflection->getProperty('tools');
        $toolsProperty->setAccessible(true);
        $tools = $toolsProperty->getValue($mcpServer);
        
        foreach ($tools as $tool) {
            $this->available_tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'enabled' => in_array($tool->getName(), $this->mcp_allowed_tools)
            ];
        }
    }
    
    public function toggleMcpEnabled(): void
    {
        $this->mcp_enabled = !$this->mcp_enabled;
        $this->saveMcpSettings();
    }
    
    public function toggleTool(string $toolName): void
    {
        if (in_array($toolName, $this->mcp_allowed_tools)) {
            $this->mcp_allowed_tools = array_values(array_diff($this->mcp_allowed_tools, [$toolName]));
        } else {
            $this->mcp_allowed_tools[] = $toolName;
        }
        
        // Update the enabled status in available_tools for UI reactivity
        foreach ($this->available_tools as &$tool) {
            if ($tool['name'] === $toolName) {
                $tool['enabled'] = in_array($toolName, $this->mcp_allowed_tools);
                break;
            }
        }
    }
    
    public function saveMcpSettings(): void
    {
        $this->validate();
        
        $settings = Settings::first();
        
        $settings->mcp_enabled = $this->mcp_enabled;
        $settings->mcp_rate_limit = $this->mcp_rate_limit;
        $settings->mcp_timeout = $this->mcp_timeout;
        $settings->mcp_allowed_tools = json_encode($this->mcp_allowed_tools);
        $settings->mcp_logging_enabled = $this->mcp_logging_enabled;
        $settings->mcp_log_level = $this->mcp_log_level;
        $settings->mcp_max_response_size = $this->mcp_max_response_size;
        $settings->mcp_require_team_context = $this->mcp_require_team_context;
        
        // Process CORS origins
        if (!empty($this->mcp_cors_origins)) {
            $origins = array_map('trim', explode("\n", $this->mcp_cors_origins));
            $origins = array_filter($origins); // Remove empty lines
            $settings->mcp_cors_origins = json_encode($origins);
        } else {
            $settings->mcp_cors_origins = null;
        }
        
        $settings->save();
        
        $this->dispatch('saved');
    }
    
    public function render(): View
    {
        return view('livewire.system.mcp-server');
    }
}