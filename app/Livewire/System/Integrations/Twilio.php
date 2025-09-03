<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Models\DataSource;
use Illuminate\View\View;
use Livewire\Component;

class Twilio extends Component
{
    public $twilio_account_sid = '';

    public $twilio_auth_token = '';

    public $twilio_from_number = '';

    protected DataSource $datasource;

    public function mount(): void
    {
        $this->datasource = DataSource::firstOrFail();

        // Decrypt and load existing values
        $this->twilio_account_sid = $this->datasource->twilio_account_sid
            ? decrypt($this->datasource->twilio_account_sid)
            : '';

        $this->twilio_auth_token = $this->datasource->twilio_auth_token
            ? decrypt($this->datasource->twilio_auth_token)
            : '';

        $this->twilio_from_number = $this->datasource->twilio_from_number ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'twilio_account_sid' => 'nullable|string|max:255',
            'twilio_auth_token' => 'nullable|string|max:255',
            'twilio_from_number' => 'nullable|string|max:20',
        ]);

        // Encrypt sensitive data before saving
        $this->datasource->twilio_account_sid = $this->twilio_account_sid
            ? encrypt($this->twilio_account_sid)
            : null;

        $this->datasource->twilio_auth_token = $this->twilio_auth_token
            ? encrypt($this->twilio_auth_token)
            : null;

        $this->datasource->twilio_from_number = $this->twilio_from_number ?: null;

        $this->datasource->save();

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.integrations.twilio');
    }
}
