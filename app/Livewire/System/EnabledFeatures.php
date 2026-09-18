<?php

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\Stats\Helpers;
use App\Services\FeatureFlags;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class EnabledFeatures extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    public bool $transcription = false;

    public bool $screencaptures = false;

    public bool $mcp = false;

    public bool $wctp_gateway = false;

    public function toggleTranscriptionFeature(): void
    {
        $this->transcription = app(FeatureFlags::class)->toggle('transcription');

        $this->dispatch('saved');
    }

    public function toggleMcpFeature(): void
    {
        $this->mcp = app(FeatureFlags::class)->toggle('mcp-server');

        $this->dispatch('saved');
    }

    public function toggleScreencapturesFeature(): void
    {
        $this->screencaptures = app(FeatureFlags::class)->toggle('screencaptures');

        $this->dispatch('saved');
    }

    public function toggleWctpGatewayFeature(): void
    {
        $this->wctp_gateway = app(FeatureFlags::class)->toggle('wctp-gateway');

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
