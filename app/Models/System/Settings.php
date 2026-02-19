<?php

namespace App\Models\System;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $switch_data_timezone
 * @property bool $mcp_enabled
 * @property int|null $mcp_rate_limit
 * @property int|null $mcp_timeout
 * @property array|null $mcp_allowed_tools
 * @property bool $mcp_logging_enabled
 * @property string|null $mcp_log_level
 * @property int|null $mcp_max_response_size
 * @property bool $mcp_require_team_context
 * @property array|null $mcp_cors_origins
 * @property int|null $saml2_enabled
 * @property string|null $saml2_metadata_url
 * @property string|null $saml2_metadata_xml
 * @property int|null $saml2_sp_sign_assertions
 * @property string|null $saml2_sp_certificate
 * @property string|null $saml2_sp_private_key
 * @property bool|null $saml2_stateless_redirect
 * @property bool|null $saml2_stateless_callback
 * @property string|null $api_whitelist
 * @property bool|null $require_api_tokens
 * @property string|null $board_check_people_praise_export_method
 * @property string|null $better_emails_canspam_address
 * @property string|null $better_emails_canspam_address2
 * @property string|null $better_emails_canspam_city
 * @property string|null $better_emails_canspam_state
 * @property string|null $better_emails_canspam_postal
 * @property string|null $better_emails_canspam_country
 * @property string|null $better_emails_canspam_email
 * @property string|null $better_emails_canspam_phone
 * @property string|null $better_emails_canspam_company
 */
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
