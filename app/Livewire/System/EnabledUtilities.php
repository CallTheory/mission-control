<?php

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\Stats\Helpers;
use App\Services\FeatureFlags;
use Illuminate\View\View;
use Livewire\Component;

class EnabledUtilities extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    public bool $api_gateway = false;

    public bool $board_check = false;

    public bool $call_lookup = false;

    public bool $card_processing = false;

    public bool $database_health = false;

    public bool $directory_search = false;

    public bool $cloud_faxing = false;

    public bool $inbound_email = false;

    public bool $script_search = false;

    public bool $better_emails = false;

    public bool $csv_export = false;

    public bool $config_editor = false;

    public bool $voicemail_digest = false;

    public bool $message_export = false;

    public function toggleConfigEditorUtility(): void
    {
        $this->config_editor = app(FeatureFlags::class)->toggle('config-editor');

        $this->dispatch('saved');
    }

    public function toggleVoicemailDigestUtility(): void
    {
        $this->voicemail_digest = app(FeatureFlags::class)->toggle('voicemail-digest');

        $this->dispatch('saved');
    }

    public function toggleCsvExportUtility(): void
    {
        $this->csv_export = app(FeatureFlags::class)->toggle('csv-export');

        $this->dispatch('saved');
    }

    public function toggleApiGatewayUtility(): void
    {
        $this->api_gateway = app(FeatureFlags::class)->toggle('api-gateway');

        $this->dispatch('saved');
    }

    public function toggleBoardCheckUtility(): void
    {
        $this->board_check = app(FeatureFlags::class)->toggle('board-check');

        $this->dispatch('saved');
    }

    public function toggleCardProcessingUtility(): void
    {
        $this->card_processing = app(FeatureFlags::class)->toggle('card-processing');

        $this->dispatch('saved');
    }

    public function toggleCloudFaxingUtility(): void
    {
        $this->cloud_faxing = app(FeatureFlags::class)->toggle('cloud-faxing');

        $this->dispatch('saved');
    }

    public function toggleInboundEmailUtility(): void
    {
        $this->inbound_email = app(FeatureFlags::class)->toggle('inbound-email');

        $this->dispatch('saved');
    }

    public function toggleScriptSearchUtility(): void
    {
        $this->script_search = app(FeatureFlags::class)->toggle('script-search');

        $this->dispatch('saved');
    }

    public function toggleBetterEmailsUtility(): void
    {
        $this->better_emails = app(FeatureFlags::class)->toggle('better-emails');

        $this->dispatch('saved');
    }

    public function toggleCallLookupUtility(): void
    {
        $this->call_lookup = app(FeatureFlags::class)->toggle('call-lookup');

        $this->dispatch('saved');
    }

    public function toggleDatabaseHealthUtility(): void
    {
        $this->database_health = app(FeatureFlags::class)->toggle('database-health');

        $this->dispatch('saved');
    }

    public function toggleDirectorySearchUtility(): void
    {
        $this->directory_search = app(FeatureFlags::class)->toggle('directory-search');

        $this->dispatch('saved');
    }

    public function toggleMessageExportUtility(): void
    {
        $this->message_export = app(FeatureFlags::class)->toggle('message-export');

        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->api_gateway = Helpers::isSystemFeatureEnabled('api-gateway');
        $this->board_check = Helpers::isSystemFeatureEnabled('board-check');
        $this->card_processing = Helpers::isSystemFeatureEnabled('card-processing');
        $this->cloud_faxing = Helpers::isSystemFeatureEnabled('cloud-faxing');
        $this->inbound_email = Helpers::isSystemFeatureEnabled('inbound-email');
        $this->script_search = Helpers::isSystemFeatureEnabled('script-search');
        $this->better_emails = Helpers::isSystemFeatureEnabled('better-emails');
        $this->call_lookup = Helpers::isSystemFeatureEnabled('call-lookup');
        $this->database_health = Helpers::isSystemFeatureEnabled('database-health');
        $this->directory_search = Helpers::isSystemFeatureEnabled('directory-search');
        $this->config_editor = Helpers::isSystemFeatureEnabled('config-editor');
        $this->csv_export = Helpers::isSystemFeatureEnabled('csv-export');
        $this->voicemail_digest = Helpers::isSystemFeatureEnabled('voicemail-digest');
        $this->message_export = Helpers::isSystemFeatureEnabled('message-export');
    }

    public function render(): View
    {
        return view('livewire.system.enabled-utilities');
    }
}
