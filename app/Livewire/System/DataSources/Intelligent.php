<?php

declare(strict_types=1);

namespace App\Livewire\System\DataSources;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Livewire\Concerns\EditsDataSourceSettings;
use App\Models\DataSource;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class Intelligent extends Component implements HasActions, HasSchemas
{
    use AuthorizesSystemComponent;
    use EditsDataSourceSettings;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public string $connectionStatus = '';

    public string $connectionMessage = '';

    protected function requiredCapability(): Capability
    {
        return Capability::SystemDataSources;
    }

    protected function settingsFields(): array
    {
        return ['is_db_host', 'is_db_port', 'is_db_data', 'is_db_user', 'is_db_pass'];
    }

    /**
     * The password is never sent to the browser and is only written when retyped.
     */
    protected function preservedFields(): array
    {
        return ['is_db_pass'];
    }

    protected function settingsSchema(): array
    {
        return [
            TextInput::make('is_db_host')
                ->label('Host Server')
                ->required()
                ->validationAttribute('host server'),

            TextInput::make('is_db_port')
                ->label('Port')
                ->numeric()
                ->required()
                ->validationAttribute('port'),

            TextInput::make('is_db_data')
                ->label('Database')
                ->required()
                ->validationAttribute('database'),

            TextInput::make('is_db_user')
                ->label('Username')
                ->required()
                ->validationAttribute('username'),

            TextInput::make('is_db_pass')
                ->label('Password')
                ->password()
                ->revealable()
                ->required()
                ->confirmed()
                ->validationAttribute('password and confirmation')
                ->helperText('Retype the password to save. It is never displayed.'),

            TextInput::make('is_db_pass_confirmation')
                ->label('Password Confirmation')
                ->password()
                ->revealable()
                ->required()
                // Confirmation is a UI concern only; it is not a column.
                ->dehydrated(false),
        ];
    }

    public function testConnection(): void
    {
        $stored = DataSource::firstOrNew();

        $host = $this->data['is_db_host'] ?: $stored->is_db_host;
        $port = $this->data['is_db_port'] ?: $stored->is_db_port;
        $database = $this->data['is_db_data'] ?: $stored->is_db_data;
        $username = $this->data['is_db_user'] ?: $stored->is_db_user;
        // The form never holds the stored password, so fall back to it when the
        // admin is testing without retyping.
        $password = $this->data['is_db_pass'] ?: ($stored->is_db_pass ?: '');

        if (empty($host) || empty($port) || empty($database) || empty($username) || empty($password)) {
            $this->connectionStatus = 'failed';
            $this->connectionMessage = 'Please fill in all connection fields.';

            return;
        }

        Config::set('database.connections.test_connection', [
            'driver' => 'sqlsrv',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'encrypt' => true,
            'trust_server_certificate' => true,
            'login_timeout' => 5,
        ]);

        try {
            DB::connection('test_connection')->getPdo();
            DB::purge('test_connection');
            $this->connectionStatus = 'success';
            $this->connectionMessage = 'Connection successful!';
        } catch (Exception $e) {
            DB::purge('test_connection');
            $this->connectionStatus = 'failed';
            $this->connectionMessage = 'Connection failed: '.$e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.system.data-sources.intelligent');
    }
}
