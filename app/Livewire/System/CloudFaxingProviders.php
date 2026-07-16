<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class CloudFaxingProviders extends Component
{
    public bool $ringcentral_enabled = false;

    public bool $mfax_enabled = false;

    public bool $ringcentral_configured = false;

    public bool $mfax_configured = false;

    public function mount(): void
    {
        $datasource = DataSource::firstOrNew();

        $this->ringcentral_enabled = (bool) $datasource->ringcentral_enabled;
        $this->mfax_enabled = (bool) $datasource->mfax_enabled;
        $this->ringcentral_configured = $datasource->ringcentral_client_id !== null;
        $this->mfax_configured = $datasource->mfax_api_key !== null;
    }

    public function toggleRingCentral(): void
    {
        $this->ringcentral_enabled = ! $this->ringcentral_enabled;
        $this->persist('ringcentral_enabled', $this->ringcentral_enabled);
    }

    public function toggleMfax(): void
    {
        $this->mfax_enabled = ! $this->mfax_enabled;
        $this->persist('mfax_enabled', $this->mfax_enabled);
    }

    private function persist(string $column, bool $value): void
    {
        $datasource = DataSource::firstOrNew();
        $datasource->{$column} = $value;
        $datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.cloud-faxing-providers');
    }
}
