<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Component;

class Twilio extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use ConfiguresDataSource;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    protected function settingsFields(): array
    {
        return ['twilio_account_sid', 'twilio_auth_token', 'twilio_from_number'];
    }

    protected function settingsHeading(): string
    {
        return 'Twilio Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'Credentials for SMS messaging and the WCTP gateway.';
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('twilio_account_sid')
                ->label('Account SID')
                ->placeholder('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx')
                ->helperText('Your Twilio Account SID from the Twilio Console.')
                ->maxLength(255),

            TextInput::make('twilio_auth_token')
                ->label('Auth Token')
                ->password()
                ->revealable()
                ->helperText('Your Twilio Auth Token. Keep this secret.')
                ->maxLength(255),

            TextInput::make('twilio_from_number')
                ->label('From Phone Number')
                ->tel()
                ->placeholder('+15551234567')
                ->helperText('The Twilio number SMS is sent from, in E.164 format.')
                ->maxLength(20),
        ];
    }

    /**
     * Whether every field needed to actually send is present.
     */
    public function isConfigured(): bool
    {
        return collect($this->currentSettings())->every(fn ($value): bool => filled($value));
    }

    public function render(): View
    {
        return view('livewire.system.integrations.twilio');
    }
}
