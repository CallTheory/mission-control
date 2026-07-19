<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Livewire\Concerns\HasSettingsModal;
use App\Livewire\Concerns\ManagesDataSourceSettings;
use Illuminate\View\View;
use Livewire\Component;

class Twilio extends Component
{
    use HasSettingsModal;
    use ManagesDataSourceSettings;

    protected array $settingsFields = [
        'twilio_account_sid',
        'twilio_auth_token',
        'twilio_from_number',
    ];

    protected function rules(): array
    {
        return [
            'state.twilio_account_sid' => 'nullable|string|max:255',
            'state.twilio_auth_token' => 'nullable|string|max:255',
            'state.twilio_from_number' => 'nullable|string|max:20',
        ];
    }

    public function save(): void
    {
        $this->validate();
        $this->persistSettings();
        $this->isOpen = false;
    }

    public function render(): View
    {
        return view('livewire.system.integrations.twilio');
    }
}
