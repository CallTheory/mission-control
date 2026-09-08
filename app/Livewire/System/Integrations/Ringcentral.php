<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use App\Models\DataSource;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Component;

class Ringcentral extends Component implements HasActions, HasSchemas
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
        return ['ringcentral_client_id', 'ringcentral_api_endpoint', 'ringcentral_client_secret', 'ringcentral_jwt_token'];
    }

    /**
     * The secret and the JWT are never displayed, and a blank submission leaves the
     * stored value alone -- which is what the previous form's `if (! empty(...))`
     * guards were doing by hand.
     */
    protected function preservedFields(): array
    {
        return ['ringcentral_client_secret', 'ringcentral_jwt_token'];
    }

    protected function settingsHeading(): string
    {
        return 'RingCentral Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'Leave the secret and JWT blank to keep the values already stored.';
    }

    /**
     * Whether a secret and JWT are already on file, so the dialog can say so rather
     * than showing an empty field that looks unconfigured.
     *
     * @return array{secret: bool, jwt: bool}
     */
    public function storedCredentials(): array
    {
        $datasource = DataSource::firstOrNew();

        return [
            'secret' => $datasource->ringcentral_client_secret !== null,
            'jwt' => $datasource->ringcentral_jwt_token !== null,
        ];
    }

    protected function settingsSchema(): array
    {
        $stored = $this->storedCredentials();

        return [
            TextInput::make('ringcentral_client_id')->label('Client ID'),

            TextInput::make('ringcentral_api_endpoint')
                ->label('API Endpoint')
                ->url()
                ->placeholder('https://platform.ringcentral.com'),

            TextInput::make('ringcentral_client_secret')
                ->label('Client Secret')
                ->password()
                ->revealable()
                ->helperText($stored['secret'] ? 'A secret is stored. Leave blank to keep it.' : 'No secret stored yet.'),

            TextInput::make('ringcentral_jwt_token')
                ->label('JWT Token')
                ->password()
                ->revealable()
                ->helperText($stored['jwt'] ? 'A token is stored. Leave blank to keep it.' : 'No token stored yet.'),
        ];
    }

    /**
     * Extends the shared save with the first-configuration behaviour: enabling the
     * provider automatically the first time a client ID is supplied.
     */
    protected function persistSettings(array $data): Model
    {
        $wasConfigured = DataSource::firstOrNew()->ringcentral_client_id !== null;

        $datasource = $this->persistDataSourceSettings($data);

        if (! $wasConfigured && filled($data['ringcentral_client_id'] ?? null)) {
            $datasource->ringcentral_enabled = true;
            $datasource->save();
        }

        return $datasource;
    }

    public function render(): View
    {
        return view('livewire.system.integrations.ringcentral');
    }
}
