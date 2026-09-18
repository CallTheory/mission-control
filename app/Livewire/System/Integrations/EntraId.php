<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use App\Models\DataSource;
use App\Services\Azure\GraphClient;
use App\Services\Azure\GraphCredentials;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * The Microsoft Entra ID (Azure AD) app registration the token watcher reads with.
 *
 * Read-only by design: the registration behind these credentials holds one Graph
 * application permission, Application.Read.All, and nothing in Mission Control ever
 * writes to Azure. See System -> Azure Tokens for what it produces.
 */
class EntraId extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use ConfiguresDataSource;
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public ?string $testResult = null;

    #[Locked]
    public ?string $testError = null;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    protected function settingsFields(): array
    {
        return ['azure_tenant_id', 'azure_client_id', 'azure_client_secret', 'azure_enabled'];
    }

    /**
     * The secret is never rendered back into the page, and a blank submission leaves
     * the stored value alone.
     */
    protected function preservedFields(): array
    {
        return ['azure_client_secret'];
    }

    protected function settingsHeading(): string
    {
        return 'Microsoft Entra ID Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'Client credentials for the app registration that reads credential expiry '
            .'from Microsoft Graph. Needs the Application.Read.All application permission '
            .'with admin consent, and nothing else. Leave the secret blank to keep the '
            .'value already stored.';
    }

    protected function settingsSchema(): array
    {
        $hasSecret = DataSource::firstOrNew()->azure_client_secret !== null;

        return [
            TextInput::make('azure_tenant_id')
                ->label('Tenant ID')
                ->placeholder('00000000-0000-0000-0000-000000000000')
                ->helperText('The directory (tenant) ID of the Entra tenant to watch.'),

            TextInput::make('azure_client_id')
                ->label('Client ID')
                ->placeholder('00000000-0000-0000-0000-000000000000')
                ->helperText('Application (client) ID of the watcher app registration.'),

            TextInput::make('azure_client_secret')
                ->label('Client Secret')
                ->password()
                ->revealable()
                ->helperText($hasSecret
                    ? 'A secret is stored. Leave blank to keep it.'
                    : 'No secret stored yet. Use the longest expiry Azure allows -- the watcher monitors its own secret too.'),

            Toggle::make('azure_enabled')
                ->label('Run the daily sweep')
                ->helperText('When off, credentials stay stored but nothing is read from Graph.'),
        ];
    }

    public function isConfigured(): bool
    {
        return GraphCredentials::fromDataSource()->configured();
    }

    public function isEnabled(): bool
    {
        return GraphCredentials::fromDataSource()->enabled;
    }

    /**
     * Proves the credentials and the admin consent in one call, which is the only
     * way to tell "wrong secret" from "permission never consented" before the first
     * sweep runs at six in the morning.
     */
    public function testConnection(): void
    {
        $this->authorize($this->requiredCapability()->value);

        $this->testResult = null;
        $this->testError = null;

        $client = new GraphClient;

        // Ignore any cached token: a secret revoked in Azure would otherwise keep
        // testing green for the rest of the hour.
        $client->forgetToken();

        try {
            $count = $client->applicationCount();

            $this->testResult = $count === null
                ? 'Authenticated to Microsoft Graph and read the applications list.'
                : 'Authenticated to Microsoft Graph. The tenant has '.$count.' app registration'
                    .($count === 1 ? '' : 's').'.';
        } catch (Throwable $e) {
            $this->testError = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.system.integrations.entra-id');
    }
}
