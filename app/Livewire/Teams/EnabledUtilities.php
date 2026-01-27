<?php

namespace App\Livewire\Teams;

use App\Models\Stats\Helpers;
use App\Models\Team;
use Illuminate\View\View;
use Livewire\Component;
use Mockery\Exception;

class EnabledUtilities extends Component
{
    public bool $api_gateway = false;

    public bool $better_emails = false;

    public bool $board_check = false;

    public bool $call_lookup = false;

    public bool $card_processing = false;

    public bool $cloud_faxing = false;

    public bool $config_editor = false;

    public bool $csv_export = false;

    public bool $database_health = false;

    public bool $directory_search = false;

    public bool $inbound_email = false;

    public bool $mcp_server = false;

    public bool $voicemail_digest = false;

    public bool $script_search = false;

    public bool $wctp_gateway = false;

    public function toggleSetting(string $setting): void
    {
        $this->$setting = ! $this->$setting;

        $team = request()->user()->currentTeam;

        switch ($setting) {
            case 'api_gateway':
                $team->utility_api_gateway = $this->api_gateway;
                break;
            case 'better_emails':
                $team->utility_better_emails = $this->better_emails;
                break;
            case 'board_check':
                $team->utility_board_check = $this->board_check;
                break;
            case 'call_lookup':
                $team->utility_call_lookup = $this->call_lookup;
                break;
            case 'card_processing':
                $team->utility_card_processing = $this->card_processing;
                break;
            case 'cloud_faxing':
                $team->utility_cloud_faxing = $this->cloud_faxing;
                break;
            case 'config_editor':
                $team->utility_config_editor = $this->config_editor;
                break;
            case 'csv_export':
                $team->utility_csv_export = $this->csv_export;
                break;
            case 'database_health':
                $team->utility_database_health = $this->database_health;
                break;
            case 'directory_search':
                $team->utility_directory_search = $this->directory_search;
                break;
            case 'inbound_email':
                $team->utility_inbound_email = $this->inbound_email;
                break;
            case 'mcp_server':
                $team->utility_mcp_server = $this->mcp_server;
                break;
            case 'voicemail_digest':
                $team->utility_voicemail_digest = $this->voicemail_digest;
                break;
            case 'script_search':
                $team->utility_script_search = $this->script_search;
                break;
            case 'wctp_gateway':
                $team->utility_wctp_gateway = $this->wctp_gateway;
                break;
            default:
                abort(400);
                break;
        }

        try {
            $team->save();
            $this->dispatch('saved');
        } catch (Exception $e) {
            $this->addError('error', 'There was an error saving the setting');
        }
    }

    public function mount(Team $team): void
    {

        if (Helpers::isSystemFeatureEnabled('api-gateway')) {
            $this->api_gateway = $team->utility_api_gateway ?? false;
        } else {
            $this->api_gateway = false;
        }

        if (Helpers::isSystemFeatureEnabled('better-emails')) {
            $this->better_emails = $team->utility_better_emails ?? false;
        } else {
            $this->better_emails = false;
        }

        if (Helpers::isSystemFeatureEnabled('board-check')) {
            $this->board_check = $team->utility_board_check ?? false;
        } else {
            $this->board_check = false;
        }

        if (Helpers::isSystemFeatureEnabled('call-lookup')) {
            $this->call_lookup = $team->utility_call_lookup ?? false;
        } else {
            $this->call_lookup = false;
        }

        if (Helpers::isSystemFeatureEnabled('card-processing')) {
            $this->card_processing = $team->utility_card_processing ?? false;
        } else {
            $this->card_processing = false;
        }

        if (Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            $this->cloud_faxing = $team->utility_cloud_faxing ?? false;
        } else {
            $this->cloud_faxing = false;
        }

        if (Helpers::isSystemFeatureEnabled('config-editor')) {
            $this->config_editor = $team->utility_config_editor ?? false;
        } else {
            $this->config_editor = false;
        }

        if (Helpers::isSystemFeatureEnabled('database-health')) {
            $this->database_health = $team->utility_database_health ?? false;
        } else {
            $this->database_health = false;
        }

        if (Helpers::isSystemFeatureEnabled('directory-search')) {
            $this->directory_search = $team->utility_directory_search ?? false;
        } else {
            $this->directory_search = false;
        }

        if (Helpers::isSystemFeatureEnabled('inbound-email')) {
            $this->inbound_email = $team->utility_inbound_email ?? false;
        } else {
            $this->inbound_email = false;
        }

        if (Helpers::isSystemFeatureEnabled('mcp-server')) {
            $this->mcp_server = $team->utility_mcp_server ?? false;
        } else {
            $this->mcp_server = false;
        }

        if (Helpers::isSystemFeatureEnabled('voicemail-digest')) {
            $this->voicemail_digest = $team->utility_voicemail_digest ?? false;
        } else {
            $this->voicemail_digest = false;
        }

        if (Helpers::isSystemFeatureEnabled('csv-export')) {
            $this->csv_export = $team->utility_csv_export ?? false;
        } else {
            $this->csv_export = false;
        }

        if (Helpers::isSystemFeatureEnabled('script-search')) {
            $this->script_search = $team->utility_script_search ?? false;
        } else {
            $this->script_search = false;
        }

        if (Helpers::isSystemFeatureEnabled('wctp-gateway')) {
            $this->wctp_gateway = $team->utility_wctp_gateway ?? false;
        } else {
            $this->wctp_gateway = false;
        }
    }

    public function render(): View
    {
        return view('livewire.teams.enabled-utilities');
    }
}
