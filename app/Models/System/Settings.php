<?php

namespace App\Models\System;

use App\Casts\EncryptedSerialized;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
 * @property bool $observability_errors_enabled
 * @property string|null $observability_errors_dsn
 * @property string|null $observability_environment
 * @property string|null $observability_release
 * @property float $observability_errors_sample_rate
 * @property Carbon|null $observability_last_test_at
 * @property string|null $observability_last_test_status
 * @property bool $observability_tracing_enabled
 * @property string|null $observability_tracing_endpoint
 * @property string|null $observability_tracing_protocol
 * @property string|null $observability_tracing_auth_username
 * @property string|null $observability_tracing_auth_token
 * @property string|null $observability_tracing_service_name
 * @property float|null $observability_tracing_sample_rate
 * @property bool $observability_tracing_db_spans_enabled
 * @property int|null $observability_tracing_db_slow_query_ms
 * @property int|null $observability_tracing_export_timeout_ms
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
        'mcp_cors_origins',
        'observability_errors_enabled',
        'observability_environment',
        'observability_release',
        'observability_errors_sample_rate',
        'observability_last_test_at',
        'observability_last_test_status',
        'observability_tracing_enabled',
        'observability_tracing_endpoint',
        'observability_tracing_protocol',
        'observability_tracing_auth_username',
        'observability_tracing_service_name',
        'observability_tracing_sample_rate',
        'observability_tracing_db_spans_enabled',
        'observability_tracing_db_slow_query_ms',
        'observability_tracing_export_timeout_ms',
    ];

    /**
     * Credentials are encrypted at rest and transparent to callers: read and
     * write PLAINTEXT, never encrypt()/decrypt() around them.
     *
     * @var list<string>
     */
    protected $hidden = [
        'saml2_metadata_xml',
        'saml2_sp_certificate',
        'saml2_sp_private_key',
        'observability_errors_dsn',
        'observability_tracing_auth_token',
    ];

    protected $casts = [
        'saml2_metadata_xml' => EncryptedSerialized::class,
        'saml2_sp_certificate' => EncryptedSerialized::class,
        'saml2_sp_private_key' => EncryptedSerialized::class,
        'mcp_enabled' => 'boolean',
        'mcp_logging_enabled' => 'boolean',
        'mcp_require_team_context' => 'boolean',
        'mcp_allowed_tools' => 'array',
        'mcp_cors_origins' => 'array',
        'observability_errors_dsn' => EncryptedSerialized::class,
        'observability_errors_enabled' => 'boolean',
        'observability_errors_sample_rate' => 'float',
        'observability_last_test_at' => 'datetime',
        'observability_tracing_auth_token' => EncryptedSerialized::class,
        'observability_tracing_enabled' => 'boolean',
        'observability_tracing_db_spans_enabled' => 'boolean',
        'observability_tracing_sample_rate' => 'float',
    ];
}
