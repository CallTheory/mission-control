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

class Bandwidth extends Component implements HasActions, HasSchemas
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
            'bandwidth_account_id',
            'bandwidth_application_id',
            'bandwidth_from_number',
            'bandwidth_api_token',
            'bandwidth_api_secret',
            'bandwidth_callback_username',
            'bandwidth_callback_password',
            'bandwidth_callback_token',
        ];
    }

    /**
     * Secrets are never rendered back into the page, and a blank submission keeps
     * whatever is stored.
     */
    protected function preservedFields(): array
    {
        return [
            'bandwidth_api_token',
            'bandwidth_api_secret',
            'bandwidth_callback_password',
            'bandwidth_callback_token',
        ];
    }

    protected function settingsHeading(): string
    {
        return 'Bandwidth Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'SMS for the WCTP gateway via the Bandwidth v2 Messaging API. Secrets left blank keep the stored value.';
    }

    protected function settingsSchema(): array
    {
        $stored = $this->storedCredentials();

        return [
            Section::make('API Credentials')
                ->description('From the Bandwidth dashboard: Account > Credentials, and the messaging application that owns your numbers.')
                ->schema([
                    TextInput::make('bandwidth_account_id')
                        ->label('Account ID')
                        ->placeholder('5000000')
                        ->maxLength(255),

                    TextInput::make('bandwidth_application_id')
                        ->label('Application ID')
                        ->helperText('The messaging application every outbound message is sent under.')
                        ->maxLength(255),

                    TextInput::make('bandwidth_from_number')
                        ->label('From Phone Number')
                        ->tel()
                        ->placeholder('+15551234567')
                        ->helperText('Used when an enterprise host has no number of its own assigned.')
                        ->maxLength(20),

                    TextInput::make('bandwidth_api_token')
                        ->label('API Token')
                        ->password()
                        ->revealable()
                        ->helperText($stored['api_token'] ? 'A token is stored. Leave blank to keep it.' : 'No token stored yet.')
                        ->maxLength(255),

                    TextInput::make('bandwidth_api_secret')
                        ->label('API Secret')
                        ->password()
                        ->revealable()
                        ->helperText($stored['api_secret'] ? 'A secret is stored. Leave blank to keep it.' : 'No secret stored yet.')
                        ->maxLength(255),
                ]),

            Section::make('Callback Authentication')
                ->description('How Bandwidth proves an inbound message or delivery receipt is really from Bandwidth. Set the Basic credentials on the messaging application, or append the token to the callback URL as ?token=... . Until one of these is stored, inbound webhooks are rejected.')
                ->schema([
                    TextInput::make('bandwidth_callback_username')
                        ->label('Callback Username')
                        ->maxLength(255),

                    TextInput::make('bandwidth_callback_password')
                        ->label('Callback Password')
                        ->password()
                        ->revealable()
                        ->helperText($stored['callback_password'] ? 'A password is stored. Leave blank to keep it.' : 'No password stored yet.')
                        ->maxLength(255),

                    TextInput::make('bandwidth_callback_token')
                        ->label('Callback Token')
                        ->password()
                        ->revealable()
                        ->helperText($stored['callback_token'] ? 'A token is stored. Leave blank to keep it.' : 'No token stored yet.')
                        ->maxLength(255)
                        ->suffixAction(
                            Action::make('generateBandwidthToken')
                                ->icon('heroicon-m-sparkles')
                                ->label('Generate')
                                ->action(fn (Set $set) => $set('bandwidth_callback_token', Str::random(40))),
                        ),
                ]),

            Section::make('Webhook URLs')
                ->description('Paste these into the messaging application in the Bandwidth dashboard. Bandwidth posts inbound messages and delivery receipts to the same URL, and either of these accepts both.')
                ->schema([
                    TextInput::make('inbound_url')
                        ->label('Inbound / callback URL')
                        ->readOnly()
                        ->dehydrated(false)
                        ->default(SmsWebhookUrls::inbound(SmsProvider::Bandwidth)),

                    TextInput::make('status_url')
                        ->label('Alternate receipt URL')
                        ->readOnly()
                        ->dehydrated(false)
                        ->default(SmsWebhookUrls::status(SmsProvider::Bandwidth)),
                ]),
        ];
    }

    /**
     * Which secrets are already on file, so a blank field can say "stored" rather
     * than looking unconfigured.
     *
     * @return array<string, bool>
     */
    public function storedCredentials(): array
    {
        $datasource = DataSource::firstOrNew();

        return [
            'api_token' => filled($datasource->bandwidth_api_token),
            'api_secret' => filled($datasource->bandwidth_api_secret),
            'callback_password' => filled($datasource->bandwidth_callback_password),
            'callback_token' => filled($datasource->bandwidth_callback_token),
        ];
    }

    /**
     * Whether every field needed to actually send is present. Asked of the gateway
     * itself so the tile cannot disagree with what the sender will do.
     */
    public function isConfigured(): bool
    {
        return app(SmsGatewayManager::class)->gateway(SmsProvider::Bandwidth)->isConfigured();
    }

    public function render(): View
    {
        return view('livewire.system.integrations.bandwidth');
    }
}
