<?php

namespace App\Livewire\System;

use App\Models\System\Settings;
use Illuminate\View\View;
use Livewire\Component;

class ApiGateway extends Component
{

    public bool $require_api_tokens = false;
    public string|null $api_whitelist;

    public function toggleApiTokens(): void
    {
        $settings = Settings::first();
        $this->require_api_tokens = !$this->require_api_tokens;
        $settings->require_api_tokens = $this->require_api_tokens;
        $settings->save();
        $this->dispatch('saved');
    }
    public function saveAPISecuritySettings(): void
    {
        $settings = Settings::first();

        if(strlen($this->api_whitelist) > 0){
            $settings->api_whitelist = json_encode(explode("\n", $this->api_whitelist));
        }else{
            $settings->api_whitelist = null;
        }

        $settings->require_api_tokens = $this->require_api_tokens;
        $settings->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $settings = Settings::first();
        if($settings->api_whitelist){
            $this->api_whitelist = implode("\n", json_decode($settings->api_whitelist));
        }
        else{
            $this->api_whitelist = '';
        }
        $this->require_api_tokens = $settings->require_api_tokens ?? false;
    }

    public function render(): View
    {
        return view('livewire.system.api-gateway');
    }
}
