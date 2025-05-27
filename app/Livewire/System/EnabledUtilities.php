<?php

namespace App\Livewire\System;

use Illuminate\View\View;
use Livewire\Component;
use Illuminate\Support\Facades\Storage;
use App\Models\Stats\Helpers;
class EnabledUtilities extends Component
{
    public bool $api_gateway = false;
    public bool $board_check = false;
    public bool $call_lookup = false;
    public bool $card_processing = false;
    public bool $database_health = false;
    public bool $directory_search = false;
    public bool $cloud_faxing = false;
    public bool $inbound_email = false;
    public bool $script_search = false;
    public bool $wctp_gateway = false;
    public bool $better_emails = false;

    public bool $csv_export = false;

    public function toggleCsvExportUtility(): void
    {
        if(Storage::fileExists('feature-flags/csv-export.flag')){
            Storage::delete('feature-flags/csv-export.flag');
            $this->csv_export = false;
        }
        else{
            Storage::put('feature-flags/csv-export.flag', encrypt('csv-export'));
            $this->csv_export = true;
        }

        $this->dispatch('saved');
    }
    public function toggleApiGatewayUtility(): void
    {
        if(Storage::fileExists('feature-flags/api-gateway.flag')){
            Storage::delete('feature-flags/api-gateway.flag');
            $this->api_gateway = false;
        }
        else{
            Storage::put('feature-flags/api-gateway.flag', encrypt('api-gateway'));
            $this->api_gateway = true;
        }

        $this->dispatch('saved');
    }
    public function toggleBoardCheckUtility(): void
    {
        if(Storage::fileExists('feature-flags/board-check.flag')){
            Storage::delete('feature-flags/board-check.flag');
            $this->board_check = false;
        }
        else{
            Storage::put('feature-flags/board-check.flag', encrypt('board-check'));
            $this->board_check = true;
        }

        $this->dispatch('saved');
    }
    public function toggleCardProcessingUtility(): void
    {
        if(Storage::fileExists('feature-flags/card-processing.flag')){
            Storage::delete('feature-flags/card-processing.flag');
            $this->card_processing = false;
        }
        else{
            Storage::put('feature-flags/card-processing.flag', encrypt('card-processing'));
            $this->card_processing = true;
        }

        $this->dispatch('saved');
    }
    public function toggleCloudFaxingUtility(): void
    {
        if(Storage::fileExists('feature-flags/cloud-faxing.flag')){
            Storage::delete('feature-flags/cloud-faxing.flag');
            $this->cloud_faxing = false;
        }
        else{
            Storage::put('feature-flags/cloud-faxing.flag', encrypt('cloud-faxing'));
            $this->cloud_faxing = true;
        }

        $this->dispatch('saved');
    }
    public function toggleInboundEmailUtility(): void
    {
        if(Storage::fileExists('feature-flags/inbound-email.flag')){
            Storage::delete('feature-flags/inbound-email.flag');
            $this->inbound_email = false;
        }
        else{
            Storage::put('feature-flags/inbound-email.flag', encrypt('inbound-email'));
            $this->inbound_email = true;
        }

        $this->dispatch('saved');
    }
    public function toggleScriptSearchUtility(): void
    {
        if(Storage::fileExists('feature-flags/script-search.flag')){
            Storage::delete('feature-flags/script-search.flag');
            $this->script_search = false;
        }
        else{
            Storage::put('feature-flags/script-search.flag', encrypt('script-search'));
            $this->script_search = true;
        }

        $this->dispatch('saved');
    }

    public function toggleWctpGatewayUtility(): void
    {
        if(Storage::fileExists('feature-flags/wctp-gateway.flag')){
            Storage::delete('feature-flags/wctp-gateway.flag');
            $this->script_search = false;
        }
        else{
            Storage::put('feature-flags/wctp-gateway.flag', encrypt('wctp-gateway'));
            $this->script_search = true;
        }

        $this->dispatch('saved');
    }

    public function toggleBetterEmailsUtility(): void
    {
        if(Storage::fileExists('feature-flags/better-emails.flag')){
            Storage::delete('feature-flags/better-emails.flag');
            $this->better_emails = false;
        }
        else{
            Storage::put('feature-flags/better-emails.flag', encrypt('better-emails'));
            $this->better_emails = true;
        }

        $this->dispatch('saved');
    }

    public function toggleCallLookupUtility(): void
    {
        if(Storage::fileExists('feature-flags/call-lookup.flag')){
            Storage::delete('feature-flags/call-lookup.flag');
            $this->call_lookup = false;
        }
        else{
            Storage::put('feature-flags/call-lookup.flag', encrypt('call-lookup'));
            $this->call_lookup = true;
        }

        $this->dispatch('saved');
    }

    public function toggleDatabaseHealthUtility(): void
    {
        if(Storage::fileExists('feature-flags/database-health.flag')){
            Storage::delete('feature-flags/database-health.flag');
            $this->database_health = false;
        }
        else{
            Storage::put('feature-flags/database-health.flag', encrypt('database-health'));
            $this->database_health = true;
        }

        $this->dispatch('saved');
    }

    public function toggleDirectorySearchUtility(): void
    {
        if(Storage::fileExists('feature-flags/directory-search.flag')){
            Storage::delete('feature-flags/directory-search.flag');
            $this->directory_search = false;
        }
        else{
            Storage::put('feature-flags/directory-search.flag', encrypt('directory-search'));
            $this->directory_search = true;
        }

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
        $this->wctp_gateway = Helpers::isSystemFeatureEnabled('wctp-gateway');
        $this->better_emails = Helpers::isSystemFeatureEnabled('better-emails');
        $this->call_lookup = Helpers::isSystemFeatureEnabled('call-lookup');
        $this->database_health = Helpers::isSystemFeatureEnabled('database-health');
        $this->directory_search = Helpers::isSystemFeatureEnabled('directory-search');
        $this->csv_export = Helpers::isSystemFeatureEnabled('csv-export');
    }


    public function render(): View
    {
        return view('livewire.system.enabled-utilities');
    }
}
