<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Enums\FaxProvider;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\FaxSpoolSource;
use App\Services\Faxing\FaxSourceHealth;
use App\Services\Faxing\Spool\SpoolFilesystemFactory;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Throwable;

/**
 * Manage the places faxes arrive from.
 *
 * Adding a second Intelligent Series fax server used to be an fstab entry and an SSH
 * session; this makes it a form, and — more usefully — makes "can we actually reach it?"
 * a question the screen can answer rather than something inferred from faxes going
 * missing.
 */
class FaxSpoolSources extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesSystemComponent;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => FaxSpoolSource::query())
            ->defaultSort('key')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (FaxSpoolSource $record): string => $record->usesSmb()
                        ? "//{$record->smb_host}"
                        : $record->rootPath()),

                TextColumn::make('key')->fontFamily('mono')->searchable()->copyable(),

                TextColumn::make('driver')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === FaxSpoolSource::DRIVER_SMB ? 'SMB' : 'Local')
                    ->color(fn (string $state): string => $state === FaxSpoolSource::DRIVER_SMB ? 'info' : 'gray'),

                TextColumn::make('pinned_provider')
                    ->label('Provider')
                    // Blank is the interesting case: the provider is chosen per fax rather
                    // than fixed by which directory the fax happened to land in.
                    ->formatStateUsing(fn (?FaxProvider $state): string => $state?->label() ?? 'Routed')
                    ->badge()
                    ->color(fn (?FaxProvider $state): string => $state === null ? 'success' : 'gray'),

                TextColumn::make('health')
                    ->label('Status')
                    ->badge()
                    ->state(fn (FaxSpoolSource $record): string => $this->healthLabel($record))
                    ->color(fn (string $state): string => match (true) {
                        $state === 'Answering' => 'success',
                        $state === 'Not checked yet' => 'gray',
                        default => 'danger',
                    })
                    ->tooltip(fn (FaxSpoolSource $record): ?string => app(FaxSourceHealth::class)->status($record->key)['last_error']),

                IconColumn::make('enabled')->boolean(),
            ])
            ->headerActions([
                $this->createSourceAction(),
            ])
            ->recordActions([
                $this->testConnectionAction(),
                $this->editSourceAction(),
                $this->toggleEnabledAction(),
                $this->deleteSourceAction(),
            ]);
    }

    public function createSourceAction(): Action
    {
        return Action::make('createSource')
            ->label('Add Fax Server')
            ->modalHeading('Add Fax Server')
            ->schema($this->sourceSchema())
            ->action(function (array $data): void {
                Gate::authorize(Capability::SystemAccess->value);

                $source = FaxSpoolSource::create($this->attributes($data));

                // Amtelco writes into these over Samba; a share pointing at a path that
                // does not exist fails in a way that reads as a fax problem rather than a
                // setup one.
                $created = $source->ensureFolders();

                Notification::make()
                    ->title('Fax server added.')
                    ->body($created === [] ? null : 'Created spool folders: '.implode(', ', $created).'.')
                    ->success()
                    ->send();
            });
    }

    public function testConnectionAction(): Action
    {
        return Action::make('testConnection')
            ->label('Test Connection')
            ->link()
            ->action(fn (FaxSpoolSource $record) => $this->testConnection($record));
    }

    public function editSourceAction(): Action
    {
        return Action::make('editSource')
            ->label('Edit')
            ->link()
            ->modalHeading('Edit Fax Server')
            ->fillForm(fn (FaxSpoolSource $record): array => [
                'name' => $record->name,
                'key' => $record->key,
                'driver' => $record->driver,
                'root_path' => $record->root_path,
                'pinned_provider' => $record->pinned_provider?->value,
                'smb_host' => $record->smb_host,
                'smb_username' => $record->smb_username,
                // Never prefilled: it would put the stored password into the DOM.
                'smb_password' => '',
                'smb_domain' => $record->smb_domain,
                'timeout_seconds' => $record->timeout_seconds,
                'enabled' => $record->enabled,
            ])
            ->schema($this->sourceSchema(isEdit: true))
            ->action(function (FaxSpoolSource $record, array $data): void {
                Gate::authorize(Capability::SystemAccess->value);

                $attributes = $this->attributes($data);

                // Blank means "keep the stored password", as the field says.
                if (blank($attributes['smb_password'] ?? null)) {
                    unset($attributes['smb_password']);
                }

                // The key is the spool directory segment and the queue lock namespace;
                // renaming it would orphan in-flight faxes and the locks holding them.
                unset($attributes['key']);

                $record->update($attributes);
                $record->refresh()->ensureFolders();

                Notification::make()->title('Fax server updated.')->success()->send();
            });
    }

    public function toggleEnabledAction(): Action
    {
        return Action::make('toggleEnabled')
            ->label(fn (FaxSpoolSource $record): string => $record->enabled ? 'Disable' : 'Enable')
            ->link()
            ->action(function (FaxSpoolSource $record): void {
                Gate::authorize(Capability::SystemAccess->value);

                $record->update(['enabled' => ! $record->enabled]);

                Notification::make()
                    ->title("{$record->name} ".($record->enabled ? 'enabled' : 'disabled').'.')
                    ->success()
                    ->send();
            });
    }

    public function deleteSourceAction(): Action
    {
        return Action::make('deleteSource')
            ->label('Delete')
            ->link()
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Faxes already recorded against this server keep their history, and its '
                .'spool folders are left untouched on disk.')
            // The two seeded sources are the original provider directories. Every
            // pre-existing install depends on them, and their keys are referenced by
            // pending faxes and by queue lock names.
            ->visible(fn (FaxSpoolSource $record): bool => ! $record->isLegacy())
            ->action(function (FaxSpoolSource $record): void {
                Gate::authorize(Capability::SystemAccess->value);

                $record->delete();

                Notification::make()->title('Fax server removed.')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    private function sourceSchema(bool $isEdit = false): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),

            TextInput::make('key')
                ->required()
                ->disabled($isEdit)
                ->dehydrated()
                ->maxLength(32)
                ->rule('regex:/^[a-z0-9][a-z0-9_-]*$/')
                ->helperText('Used as the spool directory name and cannot be changed later.'),

            Select::make('driver')
                ->required()
                ->default(FaxSpoolSource::DRIVER_LOCAL)
                ->live()
                ->options([
                    FaxSpoolSource::DRIVER_LOCAL => 'Local directory (Intelligent Series writes to our share)',
                    FaxSpoolSource::DRIVER_SMB => "SMB (we read the Intelligent Series server's share)",
                ]),

            TextInput::make('root_path')
                ->label('Root path')
                ->visible(fn ($get): bool => $get('driver') === FaxSpoolSource::DRIVER_LOCAL)
                ->helperText('Leave blank for storage/app/<key>.'),

            TextInput::make('smb_host')
                ->label('Server host or IP')
                ->visible(fn ($get): bool => $get('driver') === FaxSpoolSource::DRIVER_SMB)
                ->helperText('An IP address is safer than a hostname: a stalled DNS lookup is not covered by the connection timeout.'),

            TextInput::make('smb_username')
                ->label('Username')
                ->visible(fn ($get): bool => $get('driver') === FaxSpoolSource::DRIVER_SMB),

            TextInput::make('smb_password')
                ->label('Password')
                ->password()
                ->revealable()
                ->visible(fn ($get): bool => $get('driver') === FaxSpoolSource::DRIVER_SMB)
                ->helperText($isEdit ? 'Leave blank to keep the stored password.' : null),

            TextInput::make('smb_domain')
                ->label('Domain')
                ->visible(fn ($get): bool => $get('driver') === FaxSpoolSource::DRIVER_SMB),

            TextInput::make('timeout_seconds')
                ->label('Request timeout (seconds)')
                ->numeric()
                ->default(15)
                ->visible(fn ($get): bool => $get('driver') === FaxSpoolSource::DRIVER_SMB),

            Select::make('pinned_provider')
                ->label('Provider')
                ->options(FaxProvider::options())
                ->placeholder('Chosen per fax by routing')
                ->helperText('Leave unset so Mission Control decides, instead of the folder deciding.'),

            Toggle::make('enabled')->default(true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            ...$data,
            'root_path' => blank($data['root_path'] ?? null) ? null : $data['root_path'],
            'pinned_provider' => blank($data['pinned_provider'] ?? null) ? null : $data['pinned_provider'],
            'smb_domain' => blank($data['smb_domain'] ?? null) ? null : $data['smb_domain'],
        ];
    }

    private function testConnection(FaxSpoolSource $record): void
    {
        Gate::authorize(Capability::SystemAccess->value);

        $provider = $record->pinned_provider->value ?? FaxProvider::fallback()->value;

        try {
            $probe = app(SpoolFilesystemFactory::class)->for($record, $provider)->probe();
        } catch (Throwable $e) {
            Notification::make()->title('Could not test the connection')->body($e->getMessage())->danger()->send();

            return;
        }

        $body = $probe->message;

        if ($probe->ok()) {
            $counts = collect($probe->folderCounts)->map(fn (int $n, string $f): string => "{$f}: {$n}")->implode(', ');
            $body .= " ({$probe->elapsedMs}ms) — {$counts}.";

            if (! $probe->writable) {
                // Results are reported back to Intelligent Series by rewriting the .fs
                // into sent/ or fail/, so read-only means faxes send but never confirm.
                $body .= ' Warning: the share is not writable, so delivery results cannot be reported back.';
            }

            foreach ($probe->warnings as $warning) {
                $body .= ' '.$warning;
            }
        }

        Notification::make()
            ->title($probe->ok() ? "{$record->name} is reachable" : "{$record->name} could not be reached")
            ->body($body)
            ->status($probe->ok() ? 'success' : 'danger')
            ->persistent()
            ->send();
    }

    private function healthLabel(FaxSpoolSource $record): string
    {
        $status = app(FaxSourceHealth::class)->status($record->key);

        if ($status['failures'] > 0) {
            return "Failing ({$status['failures']})";
        }

        return $status['last_healthy_at'] === null ? 'Not checked yet' : 'Answering';
    }

    public function render(): View
    {
        return view('livewire.system.fax-spool-sources');
    }
}
