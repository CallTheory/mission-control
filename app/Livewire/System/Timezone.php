<?php

namespace App\Livewire\System;

use App\Models\System\Settings;
use Illuminate\View\View;
use Livewire\Component;

class Timezone extends Component
{
    public array $state;

    public Settings  $settings;

    public function saveSwitchTimezone(): void
    {
        $this->settings->switch_data_timezone = $this->state['timezone'];
        $this->settings->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->settings = Settings::first();

        if (is_null($this->settings->id)) {
            $this->settings = new Settings;
            $this->settings->switch_data_timezone = 'UTC';
            $this->settings->save();
        }

        $this->state['timezone'] = $this->settings->switch_data_timezone;
    }

    public function render(): View
    {
        return view('livewire.system.timezone');
    }
}
