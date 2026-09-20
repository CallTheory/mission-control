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
        };
    }

    /**
     * One-line summary, shown as the hover title on the utilities grid.
     *
     * These were the label repeated back verbatim, which told a hovering user
     * nothing. Each line is drawn from that utility's page in docs/utilities/,
     * so the two say the same thing.
     */
    public function description(): string
    {
        return match ($this) {
            self::ApiGateway => 'First-party endpoints and bring-your-own-key third-party workflows, callable from Intelligent Series scripting.',
            self::BetterEmails => 'Turns Amtelco SendMessage output into themed, branded message emails.',
            self::BoardCheck => 'Quality-assurance review of the messages your agents take, with scoring and reporting.',
            self::CallLookup => 'Look up one call by its Intelligent Series call ID, with its recording and screen capture.',
            self::CardProcessing => 'Charge customer cards through Stripe from a TBS export.',
            self::CloudFaxing => 'Sends Intelligent Series faxes through mFax or RingCentral and reports delivery back.',
            self::ConfigEditor => 'Read and edit Intelligent Series sysConfig and schSchedule records, including their encrypted payloads.',
            self::CsvExport => 'Export filtered call log data to CSV for spreadsheets and external reporting.',
            self::DatabaseHealth => 'Server, edition and database metrics for the SQL Server behind Intelligent Series.',
            self::DirectorySearch => 'Search the Intelligent Series subject directory across phone, email and other contact types.',
            self::InboundEmail => 'Receives customer email and runs it through matching rules, including CSV imports into client tables.',
            self::McpServer => 'Exposes Mission Control data to AI assistants over the Model Context Protocol.',
            self::MessageExport => 'Emails a client account\'s message fields to recipients, on a schedule or on demand.',
            self::VoicemailDigest => 'Emails scheduled digests of voicemail recordings to their recipients.',
            self::ScriptSearch => 'Search Intelligent Series script elements for keywords, phrases or embedded data.',
        };
    }
}
