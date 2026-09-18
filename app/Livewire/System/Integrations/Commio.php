<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Enums\SmsProvider;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use App\Models\DataSource;
use App\Services\Sms\SmsGatewayManager;
use App\Support\SmsWebhookUrls;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;

class Commio extends Component implements HasActions, HasSchemas
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
        return [
            'commio_account_id',
            'commio_username',
            'commio_from_number',
            'commio_api_token',
            'commio_callback_username',
            'commio_callback_password',
            'commio_callback_token',
        ];
    }

    protected function preservedFields(): array
    {
        return [
            'commio_api_token',
            'commio_callback_password',
            'commio_callback_token',
        ];
    }

    protected function settingsHeading(): string
    {
        return 'Commio Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'SMS for the WCTP gateway via the Commio (thinQ) origination API. Secrets left blank keep the stored value.';
    }

    protected function settingsSchema(): array
    {
        $stored = $this->storedCredentials();

        return [
            Section::make('API Credentials')
                ->description('From the Commio portal: your account ID, portal username, and an API token generated under user settings.')
                ->schema([
                    TextInput::make('commio_account_id')
                        ->label('Account ID')
                        ->placeholder('1234')
                        ->maxLength(255),

                    TextInput::make('commio_username')
                        ->label('API Username')
                        ->helperText('The portal username the API token belongs to.')
                        ->maxLength(255),

                    TextInput::make('commio_api_token')
                        ->label('API Token')
                        ->password()
                        ->revealable()
                        ->helperText($stored['api_token'] ? 'A token is stored. Leave blank to keep it.' : 'No token stored yet.')
                        ->maxLength(255),

                    TextInput::make('commio_from_number')
                        ->label('From Phone Number')
                        ->tel()
                        ->placeholder('+15551234567')
                        ->helperText('Used when an enterprise host has no number of its own assigned.')
                        ->maxLength(20),
                ]),

            Section::make('Callback Authentication')
                ->description('How an inbound message or delivery receipt is proved to be from Commio. The portal takes a plain URL, so the usual choice is the token appended as ?token=... ; Basic credentials embedded in the URL work too. Until one of these is stored, inbound webhooks are rejected.')
                ->schema([
                    TextInput::make('commio_callback_username')
                        ->label('Callback Username')
                        ->maxLength(255),

                    TextInput::make('commio_callback_password')
                        ->label('Callback Password')
                        ->password()
                        ->revealable()
                        ->helperText($stored['callback_password'] ? 'A password is stored. Leave blank to keep it.' : 'No password stored yet.')
                        ->maxLength(255),

                    TextInput::make('commio_callback_token')
                        ->label('Callback Token')
                        ->password()
                        ->revealable()
                        ->helperText($stored['callback_token'] ? 'A token is stored. Leave blank to keep it.' : 'No token stored yet.')
                        ->maxLength(255)
                        ->suffixAction(
                            Action::make('generateCommioToken')
                                ->icon('heroicon-m-sparkles')
                                ->label('Generate')
                                ->action(fn (Set $set) => $set('commio_callback_token', Str::random(40))),
                        ),
                ]),

            Section::make('Webhook URLs')
                ->description('Paste these into the Commio portal as the inbound SMS URL and the delivery-receipt URL. Either accepts both kinds of post.')
                ->schema([
                    TextInput::make('inbound_url')
                        ->label('Inbound SMS URL')
                        ->readOnly()
                        ->dehydrated(false)
                        ->default(SmsWebhookUrls::inbound(SmsProvider::Commio)),

                    TextInput::make('status_url')
                        ->label('Delivery receipt URL')
                        ->readOnly()
                        ->dehydrated(false)
                        ->default(SmsWebhookUrls::status(SmsProvider::Commio)),
                ]),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function storedCredentials(): array
    {
        $datasource = DataSource::firstOrNew();

        return [
            'api_token' => filled($datasource->commio_api_token),
            'callback_password' => filled($datasource->commio_callback_password),
            'callback_token' => filled($datasource->commio_callback_token),
        ];
    }

    /**
     * Asked of the gateway itself so the tile cannot disagree with the sender.
     */
    public function isConfigured(): bool
    {
        return app(SmsGatewayManager::class)->gateway(SmsProvider::Commio)->isConfigured();
    }

    public function render(): View
    {
        return view('livewire.system.integrations.commio');
    }
}
