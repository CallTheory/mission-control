<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // MCP Server configuration
            $table->boolean('mcp_enabled')->default(false);
            $table->integer('mcp_rate_limit')->default(100)->comment('Requests per minute per API key');
            $table->integer('mcp_timeout')->default(30)->comment('Tool execution timeout in seconds');
            $table->json('mcp_allowed_tools')->nullable()->comment('JSON array of enabled tool names');
            $table->boolean('mcp_logging_enabled')->default(false);
            $table->string('mcp_log_level')->default('error')->comment('Log level: error, warning, info, debug');
            $table->integer('mcp_max_response_size')->default(1048576)->comment('Max response size in bytes (1MB default)');
            $table->boolean('mcp_require_team_context')->default(true)->comment('Require valid team context for tool execution');
            $table->json('mcp_cors_origins')->nullable()->comment('Allowed CORS origins for MCP endpoints');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'mcp_enabled',
                'mcp_rate_limit',
                'mcp_timeout',
                'mcp_allowed_tools',
                'mcp_logging_enabled',
                'mcp_log_level',
                'mcp_max_response_size',
                'mcp_require_team_context',
                'mcp_cors_origins'
            ]);
        });
    }
};