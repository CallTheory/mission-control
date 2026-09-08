<?php

declare(strict_types=1);

namespace App\Livewire\System\Integrations;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\ConfiguresDataSource;
use App\Models\DataSource;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\View;
use Livewire\Component;

class Mfax extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use ConfiguresDataSource;
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemIntegrations;
    }

    /**
     * The basic auth pair is generated rather than entered, so it is not part of the
     * editable set -- it is only displayed.
     */
    protected function settingsFields(): array
    {
        return ['mfax_api_key', 'mfax_sender_name', 'mfax_subject', 'mfax_notes', 'mfax_cover_page_id'];
    }

    protected function settingsHeading(): string
    {
        return 'mFax Configuration';
    }

    protected function settingsDescription(): string
    {
        return 'API credentials and the defaults applied to outbound faxes.';
    }

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        $datasource = DataSource::firstOrNew();

        if ($datasource->mfax_basic_auth_username !== null && $datasource->mfax_basic_auth_password !== null) {
            return;
        }

        // The inbound webhook needs a credential pair whether or not anyone has opened
        // this dialog, so generate it on first view. The model cast encrypts on write.
        try {
            $datasource->mfax_basic_auth_username = bin2hex(random_bytes(8));
            $datasource->mfax_basic_auth_password = bin2hex(random_bytes(8));
            $datasource->save();
        } catch (Exception $e) {
            throw new Exception('Unable to generate auth user/pass. '.$e->getMessage());
        }
    }

    /**
     * The generated inbound credentials, shown so they can be copied into mFax.
     *
     * @return array{username: ?string, password: ?string}
     */
    public function basicAuthCredentials(): array
    {
        $datasource = DataSource::firstOrNew();

        return [
            'username' => $datasource->mfax_basic_auth_username,
            'password' => $datasource->mfax_basic_auth_password,
        ];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('mfax_api_key')
                ->label('API Key')
                ->password()
                ->revealable()
                ->helperText('From the mFax developer console.'),

            TextInput::make('mfax_sender_name')->label('Sender Name'),
            TextInput::make('mfax_subject')->label('Default Subject'),
            TextInput::make('mfax_cover_page_id')->label('Cover Page ID'),

            Textarea::make('mfax_notes')
                ->label('Default Notes')
                ->rows(3),
        ];
    }

    /**
     * Extends the shared save with the first-configuration behaviour: enabling the
     * provider automatically the first time an API key is supplied, so a fresh setup
     * is not silently switched off. Re-editing an existing key leaves the toggle alone.
     */
    protected function persistSettings(array $data): Model
    {
        $wasConfigured = DataSource::firstOrNew()->mfax_api_key !== null;

        $datasource = $this->persistDataSourceSettings($data);

        if (! $wasConfigured && filled($data['mfax_api_key'] ?? null)) {
            $datasource->mfax_enabled = true;
            $datasource->save();
        }

        return $datasource;
    }

    public function render(): View
    {
        return view('livewire.system.integrations.mfax');
    }
}
