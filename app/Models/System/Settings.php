<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'switch_data_timezone',
        'mcp_enabled',
        'mcp_rate_limit',
        'mcp_timeout',
        'mcp_allowed_tools',
        'mcp_logging_enabled',
        'mcp_log_level',
        'mcp_max_response_size',
        'mcp_require_team_context',
        'mcp_cors_origins'
    ];
    
    protected $casts = [
        'mcp_enabled' => 'boolean',
        'mcp_logging_enabled' => 'boolean',
        'mcp_require_team_context' => 'boolean',
        'mcp_allowed_tools' => 'array',
        'mcp_cors_origins' => 'array',
    ];
}
