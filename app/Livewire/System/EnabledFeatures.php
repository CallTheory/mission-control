<?php

namespace App\Livewire\System;

use App\Models\Stats\Helpers;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class EnabledFeatures extends Component
{
    public bool $transcription = false;

    public bool $screencaptures = false;

    public bool $mcp = false;

    public bool $wctp_gateway = false;

    private string $transcriptionFeatureFlagLocation = 'feature-flags/transcription.flag';

    private string $screencapturesFeatureFlagLocation = 'feature-flags/screencaptures.flag';

    private string $mcpserverFeatureFlagLocation = 'feature-flags/mcp-server.flag';

    private string $wctpGatewayFeatureFlagLocation = 'feature-flags/wctp-gateway.flag';

    public function toggleTranscriptionFeature(): void
    {
        if (Storage::fileExists($this->transcriptionFeatureFlagLocation)) {
            Storage::delete($this->transcriptionFeatureFlagLocation);
            $this->transcription = false;
        } else {
            Storage::put($this->transcriptionFeatureFlagLocation, encrypt('transcription'));
            $this->transcription = true;
        }

        $this->dispatch('saved');
    }

    public function toggleMcpFeature(): void
    {
        if (Storage::fileExists($this->mcpserverFeatureFlagLocation)) {
            Storage::delete($this->mcpserverFeatureFlagLocation);
            $this->mcp = false;
        } else {
            Storage::put($this->mcpserverFeatureFlagLocation, encrypt('mcp-server'));
            $this->mcp = true;
        }

        $this->dispatch('saved');
    }

    public function toggleScreencapturesFeature(): void
    {
        if (Storage::fileExists($this->screencapturesFeatureFlagLocation)) {
            Storage::delete($this->screencapturesFeatureFlagLocation);
            $this->screencaptures = false;
        } else {
            Storage::put($this->screencapturesFeatureFlagLocation, encrypt('screencaptures'));
            $this->screencaptures = true;
        }

        $this->dispatch('saved');
    }

    public function toggleWctpGatewayFeature(): void
    {
        if (Storage::fileExists($this->wctpGatewayFeatureFlagLocation)) {
            Storage::delete($this->wctpGatewayFeatureFlagLocation);
            $this->wctp_gateway = false;
        } else {
            Storage::put($this->wctpGatewayFeatureFlagLocation, encrypt('wctp-gateway'));
            $this->wctp_gateway = true;
        }

        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->transcription = Helpers::isSystemFeatureEnabled('transcription');
        $this->screencaptures = Helpers::isSystemFeatureEnabled('screencaptures');
        $this->mcp = Helpers::isSystemFeatureEnabled('mcp-server');
        $this->wctp_gateway = Helpers::isSystemFeatureEnabled('wctp-gateway');
    }

    public function render(): View
    {
        return view('livewire.system.enabled-features');
    }
}
