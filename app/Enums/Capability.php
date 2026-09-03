<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fixed, code-defined set of capabilities — the real authorization gate points
 * wired into controllers, Livewire components and Blade views.
 *
 * Administrators compose roles out of these; they cannot invent new
 * capabilities, because a capability only means something if code checks it.
 * Roles map to capability values via the role_capability table.
 */
enum Capability: string
{
    // System / admin areas
    case SystemAccess = 'system.access';
    case AdminManageUsers = 'admin.manage_users';
    case AdminManageRoles = 'admin.manage_roles';
    case SystemDataSources = 'system.data_sources';
    case SystemIntegrations = 'system.integrations';
    case SystemObservability = 'system.observability';

    // General access areas
    case AnalyticsView = 'analytics.view';
    case UtilitiesAccess = 'utilities.access';
    case AccountsView = 'accounts.view';
    case ApiTokensManage = 'api_tokens.manage';

    // Team management
    case TeamManage = 'team.manage';
    case TeamAddMember = 'team.add_member';

    // Utilities (one per App\Enums\Utility case; value = 'utility.'.<key>)
    case UtilityApiGateway = 'utility.api_gateway';
    case UtilityBetterEmails = 'utility.better_emails';
    case UtilityBoardCheck = 'utility.board_check';
    case UtilityCallLookup = 'utility.call_lookup';
    case UtilityCardProcessing = 'utility.card_processing';
    case UtilityCloudFaxing = 'utility.cloud_faxing';
    case UtilityConfigEditor = 'utility.config_editor';
    case UtilityCsvExport = 'utility.csv_export';
    case UtilityDatabaseHealth = 'utility.database_health';
    case UtilityDirectorySearch = 'utility.directory_search';
    case UtilityInboundEmail = 'utility.inbound_email';
    case UtilityMcpServer = 'utility.mcp_server';
    case UtilityMessageExport = 'utility.message_export';
    case UtilityVoicemailDigest = 'utility.voicemail_digest';
    case UtilityScriptSearch = 'utility.script_search';
    case UtilityWctpGateway = 'utility.wctp_gateway';

    // Board sub-pages (gated under the Board Check utility's flags)
    case BoardReview = 'board.review';
    case BoardReport = 'board.report';
    case BoardActivity = 'board.activity';

    /**
     * The UI group this capability belongs to.
     */
    public function group(): string
    {
        return match (true) {
            str_starts_with($this->value, 'utility.') => 'Utilities',
            str_starts_with($this->value, 'board.') => 'Board',
            $this === self::TeamManage, $this === self::TeamAddMember => 'Team',
            $this === self::AnalyticsView,
            $this === self::UtilitiesAccess,
            $this === self::AccountsView,
            $this === self::ApiTokensManage => 'General',
            default => 'System',
        };
    }

    /**
     * Human-friendly label for the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::SystemAccess => 'Access System Settings',
            self::SystemObservability => 'Manage Observability',
            self::AdminManageUsers => 'Manage Users',
            self::AdminManageRoles => 'Manage Roles & Permissions',
            self::SystemDataSources => 'Manage Data Sources',
            self::SystemIntegrations => 'Manage Integrations',
            self::AnalyticsView => 'View Analytics',
            self::UtilitiesAccess => 'Access Utilities',
            self::AccountsView => 'View Accounts',
            self::ApiTokensManage => 'Manage API Tokens',
            self::TeamManage => 'Manage Team',
            self::TeamAddMember => 'Add Team Members',
            self::BoardReview => 'Board Review',
            self::BoardReport => 'Board Report',
            self::BoardActivity => 'Board Activity',
            default => str_starts_with($this->value, 'utility.')
                ? Utility::from(substr($this->value, strlen('utility.')))->label()
                : $this->value,
        };
    }

    /**
     * True when this capability gates a utility (and therefore also enforces
     * the system + team feature-flag layers in its Gate definition).
     */
    public function isUtility(): bool
    {
        return str_starts_with($this->value, 'utility.');
    }

    /**
     * All capabilities grouped by group() label, preserving declaration order.
     *
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $capability) {
            $grouped[$capability->group()][] = $capability;
        }

        return $grouped;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
