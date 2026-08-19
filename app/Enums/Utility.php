<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical registry of the application's utilities.
 *
 * This is the single source of truth for the three facts that were previously
 * duplicated across app/Livewire/System/EnabledUtilities.php,
 * app/Livewire/Teams/EnabledUtilities.php, resources/views/utilities.blade.php
 * and the routes: the system feature-flag name, the per-team boolean column,
 * and the capability that gates access.
 */
enum Utility: string
{
    case ApiGateway = 'api_gateway';
    case BetterEmails = 'better_emails';
    case BoardCheck = 'board_check';
    case CallLookup = 'call_lookup';
    case CardProcessing = 'card_processing';
    case CloudFaxing = 'cloud_faxing';
    case ConfigEditor = 'config_editor';
    case CsvExport = 'csv_export';
    case DatabaseHealth = 'database_health';
    case DirectorySearch = 'directory_search';
    case InboundEmail = 'inbound_email';
    case McpServer = 'mcp_server';
    case MessageExport = 'message_export';
    case VoicemailDigest = 'voicemail_digest';
    case ScriptSearch = 'script_search';
    case WctpGateway = 'wctp_gateway';

    /**
     * The system-level feature-flag name (dash form) used by
     * Helpers::isSystemFeatureEnabled().
     */
    public function systemFlag(): string
    {
        return str_replace('_', '-', $this->value);
    }

    /**
     * The per-team boolean column on the teams table.
     */
    public function teamColumn(): string
    {
        return 'utility_'.$this->value;
    }

    /**
     * The capability that gates access to this utility.
     */
    public function capability(): Capability
    {
        return Capability::from('utility.'.$this->value);
    }

    /**
     * Human-friendly label for the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::ApiGateway => 'API Gateway',
            self::BetterEmails => 'Better Emails',
            self::BoardCheck => 'Board Check',
            self::CallLookup => 'Call Lookup',
            self::CardProcessing => 'Card Processing',
            self::CloudFaxing => 'Cloud Faxing',
            self::ConfigEditor => 'Config Editor',
            self::CsvExport => 'CSV Export',
            self::DatabaseHealth => 'Database Health',
            self::DirectorySearch => 'Directory Search',
            self::InboundEmail => 'Inbound Email',
            self::McpServer => 'MCP Server',
            self::MessageExport => 'Message Export',
            self::VoicemailDigest => 'Voicemail Digest',
            self::ScriptSearch => 'Script Search',
            self::WctpGateway => 'WCTP Gateway',
        };
    }
}
