<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\DataSource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Component;

class ConfigEditor extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public string $encryptedInput = '';

    public string $xmlContent = '';

    public string $encryptedOutput = '';

    public string $errorMessage = '';

    public array $sysConfigs = [];

    public array $scheduleRecords = [];

    public array $emailAccounts = [];

    public string $activeSource = '';

    public ?int $activeSchId = null;

    public ?int $activeEmailAccountId = null;

    public bool $databaseLoaded = false;

    public function mount(): void
    {
        $this->loadFromDatabase();
    }

    public function loadFromDatabase(): void
    {
        $this->sysConfigs = [];
        $this->scheduleRecords = [];
        $this->emailAccounts = [];
        $this->databaseLoaded = false;

        try {
            $datasource = DataSource::first();

            if (! $datasource) {
                return;
            }

            Config::set('database.connections.intelligent', [
                'driver' => 'sqlsrv',
                'host' => $datasource->is_db_host,
                'port' => $datasource->is_db_port,
                'database' => $datasource->is_db_data,
                'username' => $datasource->is_db_user,
                'password' => $datasource->is_db_pass,
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);

            $sysRows = DB::connection('intelligent')->select('SELECT TOP 1 Config, Config2 FROM sysConfig');
            if (! empty($sysRows)) {
                $row = $sysRows[0];
                $this->sysConfigs = [
                    'Config' => $row->Config ?? null,
                    'Config2' => $row->Config2 ?? null,
                ];
            }

            $schRows = DB::connection('intelligent')->select('SELECT schId, Scheduled, Action, RecordJSON FROM schSchedule WHERE RecordJSON IS NOT NULL');
            $this->scheduleRecords = array_map(fn ($row) => [
                'schId' => $row->schId,
                'Scheduled' => $row->Scheduled,
                'Action' => $row->Action,
            ], $schRows);

            $emailRows = DB::connection('intelligent')->select('SELECT ID, cltID, Name, Description FROM cltEmailAccounts WHERE Account IS NOT NULL');
            $this->emailAccounts = array_map(fn ($row) => [
                'ID' => $row->ID,
                'cltID' => $row->cltID,
                'Name' => $row->Name,
                'Description' => $row->Description,
            ], $emailRows);

            $this->databaseLoaded = true;
        } catch (\Exception) {
            // Silently fail on mount — database may not be configured
        }
    }

    public function loadSysConfig(string $field): void
    {
        $this->errorMessage = '';

        if (! in_array($field, ['Config', 'Config2']) || empty($this->sysConfigs[$field])) {
            $this->errorMessage = "No data available for {$field}.";

            return;
        }

        $this->encryptedInput = $this->sysConfigs[$field];
        $this->activeSource = 'sysConfig-'.$field;
        $this->activeSchId = null;
        $this->activeEmailAccountId = null;
        $this->decrypt();
    }

    public function loadScheduleRecord(int $schId): void
    {
        $this->errorMessage = '';

        try {
            $datasource = DataSource::firstOrFail();

            Config::set('database.connections.intelligent', [
                'driver' => 'sqlsrv',
                'host' => $datasource->is_db_host,
                'port' => $datasource->is_db_port,
                'database' => $datasource->is_db_data,
                'username' => $datasource->is_db_user,
                'password' => $datasource->is_db_pass,
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);

            $rows = DB::connection('intelligent')->select('SELECT RecordJSON FROM schSchedule WHERE schId = ?', [$schId]);

            if (empty($rows) || empty($rows[0]->RecordJSON)) {
                $this->errorMessage = "No RecordJSON found for schId {$schId}.";

                return;
            }

            $this->encryptedInput = $rows[0]->RecordJSON;
            $this->activeSource = 'schedule-'.$schId;
            $this->activeSchId = $schId;
            $this->activeEmailAccountId = null;
            $this->decrypt();
        } catch (\Exception $e) {
            $this->errorMessage = 'Load error: '.$e->getMessage();
        }
    }

    public function saveToSchedule(): void
    {
        $this->errorMessage = '';

        if ($this->activeSchId === null) {
            $this->errorMessage = 'No schedule record is currently active.';

            return;
        }

        if (empty($this->xmlContent)) {
            $this->errorMessage = 'No XML content to save.';

            return;
        }

        try {
            $this->encrypt();

            if (empty($this->encryptedOutput)) {
                return;
            }

            $datasource = DataSource::firstOrFail();

            Config::set('database.connections.intelligent', [
                'driver' => 'sqlsrv',
                'host' => $datasource->is_db_host,
                'port' => $datasource->is_db_port,
                'database' => $datasource->is_db_data,
                'username' => $datasource->is_db_user,
                'password' => $datasource->is_db_pass,
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);

            DB::connection('intelligent')->update('UPDATE schSchedule SET RecordJSON = ? WHERE schId = ?', [
                $this->encryptedOutput,
                $this->activeSchId,
            ]);

            $this->errorMessage = '';
            $this->dispatch('savedToDatabase');
        } catch (\Exception $e) {
            $this->errorMessage = 'Save error: '.$e->getMessage();
        }
    }

    public function loadEmailAccount(int $id): void
    {
        $this->errorMessage = '';

        try {
            $datasource = DataSource::firstOrFail();

            Config::set('database.connections.intelligent', [
                'driver' => 'sqlsrv',
                'host' => $datasource->is_db_host,
                'port' => $datasource->is_db_port,
                'database' => $datasource->is_db_data,
                'username' => $datasource->is_db_user,
                'password' => $datasource->is_db_pass,
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);

            $rows = DB::connection('intelligent')->select('SELECT Account FROM cltEmailAccounts WHERE ID = ?', [$id]);

            if (empty($rows) || empty($rows[0]->Account)) {
                $this->errorMessage = "No Account data found for ID {$id}.";

                return;
            }

            $this->encryptedInput = $rows[0]->Account;
            $this->activeSource = 'emailAccount-'.$id;
            $this->activeEmailAccountId = $id;
            $this->activeSchId = null;
            $this->decrypt();
        } catch (\Exception $e) {
            $this->errorMessage = 'Load error: '.$e->getMessage();
        }
    }

    public function saveToEmailAccount(): void
    {
        $this->errorMessage = '';

        if ($this->activeEmailAccountId === null) {
            $this->errorMessage = 'No email account is currently active.';

            return;
        }

        if (empty($this->xmlContent)) {
            $this->errorMessage = 'No content to save.';

            return;
        }

        try {
            $this->encrypt();

            if (empty($this->encryptedOutput)) {
                return;
            }

            $datasource = DataSource::firstOrFail();

            Config::set('database.connections.intelligent', [
                'driver' => 'sqlsrv',
                'host' => $datasource->is_db_host,
                'port' => $datasource->is_db_port,
                'database' => $datasource->is_db_data,
                'username' => $datasource->is_db_user,
                'password' => $datasource->is_db_pass,
                'encrypt' => true,
                'trust_server_certificate' => true,
            ]);

            DB::connection('intelligent')->update('UPDATE cltEmailAccounts SET Account = ? WHERE ID = ?', [
                $this->encryptedOutput,
                $this->activeEmailAccountId,
            ]);

            $this->errorMessage = '';
            $this->dispatch('savedToDatabase');
        } catch (\Exception $e) {
            $this->errorMessage = 'Save error: '.$e->getMessage();
        }
    }

    public function decrypt(): void
    {
        $this->errorMessage = '';
        $this->encryptedOutput = '';

        if (empty($this->encryptedInput)) {
            $this->errorMessage = 'Please provide encrypted text to decrypt.';

            return;
        }

        try {
            $decoded = base64_decode($this->encryptedInput, true);
            if ($decoded === false) {
                $this->errorMessage = 'Invalid Base64 input.';

                return;
            }

            $key = hex2bin(bin2hex('12345678'));
            $iv = hex2bin(bin2hex('12345678'));

            $decrypted = openssl_decrypt($decoded, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                $this->errorMessage = 'Decryption failed. The input may not be valid encrypted data.';

                return;
            }

            $json = json_decode($decrypted);
            if ($json !== null) {
                $decrypted = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $this->xmlContent = $decrypted;
        } catch (\Exception $e) {
            $this->errorMessage = 'Decryption error: '.$e->getMessage();
        }
    }

    public function encrypt(): void
    {
        $this->errorMessage = '';
        $this->encryptedOutput = '';

        if (empty($this->xmlContent)) {
            $this->errorMessage = 'Please provide XML content to encrypt.';

            return;
        }

        try {
            $key = hex2bin(bin2hex('12345678'));
            $iv = hex2bin(bin2hex('12345678'));

            $encrypted = openssl_encrypt($this->xmlContent, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $iv);

            if ($encrypted === false) {
                $this->errorMessage = 'Encryption failed.';

                return;
            }

            $this->encryptedOutput = base64_encode($encrypted);
        } catch (\Exception $e) {
            $this->errorMessage = 'Encryption error: '.$e->getMessage();
        }
    }

    /**
     * The XML editor and the two ciphertext boxes.
     *
     * The editor was a CodeMirror instance mounted by hand: a wire:ignore div, an
     * Alpine component polling for window.initConfigEditor, a bespoke resources/js
     * bundle, and an xmlUpdated event to push server-side changes back into it.
     * Filament's CodeEditor is the same editor, bound like any other field, so all of
     * that goes -- including the 424 kB chunk it needed.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('encryptedInput')
                    ->label('Encrypted Input')
                    ->rows(3)
                    ->autosize()
                    ->helperText('Base64 ciphertext from a config field or schedule record.'),

                CodeEditor::make('xmlContent')
                    ->label('Configuration XML')
                    ->language(Language::Xml),

                Textarea::make('encryptedOutput')
                    ->label('Encrypted Output')
                    ->rows(3)
                    ->autosize()
                    ->readOnly()
                    ->helperText('Paste this back into the source field.'),
            ])
            ->statePath('');
    }

    public function decryptAction(): Action
    {
        return Action::make('decrypt')
            ->label('Decrypt')
            ->action(function (): void {
                $this->decrypt();

                if ($this->errorMessage !== '') {
                    Notification::make()->title($this->errorMessage)->danger()->send();

                    return;
                }

                Notification::make()->title('Decrypted.')->success()->send();
            });
    }

    public function encryptAction(): Action
    {
        return Action::make('encrypt')
            ->label('Encrypt')
            ->color('primary')
            ->action(function (): void {
                $this->encrypt();

                if ($this->errorMessage !== '') {
                    Notification::make()->title($this->errorMessage)->danger()->send();

                    return;
                }

                Notification::make()->title('Encrypted. Copy the output below.')->success()->send();
            });
    }

    public function loadFromDatabaseAction(): Action
    {
        return Action::make('loadFromDatabase')
            ->label('Load from Database')
            ->color('gray')
            ->action(function (): void {
                $this->loadFromDatabase();

                Notification::make()
                    ->title($this->databaseLoaded ? 'Sources loaded.' : 'Unable to reach the configuration database.')
                    ->status($this->databaseLoaded ? 'success' : 'danger')
                    ->send();
            });
    }

    public function saveToScheduleAction(): Action
    {
        return Action::make('saveToSchedule')
            ->label('Save to Schedule Record')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Overwrite the schedule record?')
            ->modalDescription('This writes the encrypted XML back to the Amtelco schedule record it came from.')
            ->visible(fn (): bool => str_starts_with($this->activeSource, 'schedule:'))
            ->action(function (): void {
                $this->saveToSchedule();

                Notification::make()
                    ->title($this->errorMessage !== '' ? $this->errorMessage : 'Schedule record updated.')
                    ->status($this->errorMessage !== '' ? 'danger' : 'success')
                    ->send();
            });
    }

    public function saveToEmailAccountAction(): Action
    {
        return Action::make('saveToEmailAccount')
            ->label('Save to Email Account')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Overwrite the email account?')
            ->modalDescription('This writes the encrypted XML back to the Amtelco email account it came from.')
            ->visible(fn (): bool => str_starts_with($this->activeSource, 'email:'))
            ->action(function (): void {
                $this->saveToEmailAccount();

                Notification::make()
                    ->title($this->errorMessage !== '' ? $this->errorMessage : 'Email account updated.')
                    ->status($this->errorMessage !== '' ? 'danger' : 'success')
                    ->send();
            });
    }

    public function render(): View
    {
        return view('livewire.utilities.config-editor');
    }
}
