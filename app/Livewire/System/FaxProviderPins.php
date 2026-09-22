<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Enums\FaxProvider;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\DataSource;
use App\Models\FaxProviderPin;
use App\Services\Faxing\FaxRouter;
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

/**
 * Overrides that force particular faxes through a particular provider.
 *
 * The need is narrow and operational: when one provider's route to a destination starts
 * failing, move that destination — or that client — without disturbing anything else.
 *
 * Also carries the system default and the failover switch, because they are the rest of
 * the same decision, and shows what a given number or account would actually resolve to.
 * Once the provider stops being visible in the directory name, "which provider will this
 * fax use" has to be answerable somewhere.
 */
class FaxProviderPins extends Component implements HasActions, HasSchemas, HasTable
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
            ->query(fn (): Builder => FaxProviderPin::query())
            ->defaultSort('match_value')
            ->emptyStateHeading('No provider pins')
            ->emptyStateDescription('Faxes use the system default, or the provider their spool source is pinned to.')
            ->columns([
                TextColumn::make('match_type')
                    ->label('Match')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === FaxProviderPin::MATCH_NUMBER ? 'Number' : 'Account'),

                TextColumn::make('match_value')->label('Value')->fontFamily('mono')->searchable(),

                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (FaxProvider $state): string => $state->label()),

                IconColumn::make('allow_failover')
                    ->label('May fail over')
                    ->boolean()
                    // A pin usually exists *because* the other provider is broken for this
                    // fax, so falling back to it would undo the fix.
                    ->tooltip('Off means a failed submission is reported as failed rather than retried elsewhere.'),

                TextColumn::make('note')->wrap()->toggleable(),

                IconColumn::make('enabled')->boolean(),
            ])
            ->headerActions([
                Action::make('routingSettings')
                    ->label('Routing Settings')
                    ->modalHeading('Fax Routing')
                    ->fillForm(fn (): array => [
                        'fax_default_provider' => DataSource::first()?->fax_default_provider,
                        'fax_failover_enabled' => (bool) (DataSource::first()->fax_failover_enabled ?? false),
                    ])
                    ->schema([
                        Select::make('fax_default_provider')
                            ->label('Default provider')
                            ->options(FaxProvider::options())
                            ->placeholder('First configured provider')
                            ->helperText('Used by any spool source that is not pinned to a provider. '
                                .'Changing this needs no Intelligent Series change.'),

                        Toggle::make('fax_failover_enabled')
                            ->label('Try the other provider when a submission fails')
                            ->helperText('Off by default. A site that has only ever used one provider should not '
                                .'start using the other because of a transient error.'),
                    ])
                    ->action(function (array $data): void {
                        Gate::authorize(Capability::SystemAccess->value);

                        $datasource = DataSource::firstOrNew();
                        $datasource->fax_default_provider = $data['fax_default_provider'] ?: null;
                        $datasource->fax_failover_enabled = (bool) $data['fax_failover_enabled'];
                        $datasource->save();

                        Notification::make()->title('Routing settings saved.')->success()->send();
                    }),

                Action::make('preview')
                    ->label('Check a number')
                    ->modalHeading('Which provider would this use?')
                    ->modalSubmitActionLabel('Check')
                    ->schema([
                        TextInput::make('phone')->label('Fax number')->required(),
                    ])
                    ->action(function (array $data): void {
                        $route = app(FaxRouter::class)->route(['phone' => $data['phone']]);

                        Notification::make()
                            ->title($route->provider->label())
                            ->body("Chosen because: {$route->reason}.")
                            ->info()
                            ->persistent()
                            ->send();
                    }),

                Action::make('createPin')
                    ->label('Add Pin')
                    ->modalHeading('Add Provider Pin')
                    ->schema($this->pinSchema())
                    ->action(function (array $data): void {
                        Gate::authorize(Capability::SystemAccess->value);

                        FaxProviderPin::create($data);

                        Notification::make()->title('Pin added.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('editPin')
                    ->label('Edit')
                    ->link()
                    ->modalHeading('Edit Provider Pin')
                    ->fillForm(fn (FaxProviderPin $record): array => [
                        'match_type' => $record->match_type,
                        'match_value' => $record->match_value,
                        'provider' => $record->provider->value,
                        'allow_failover' => $record->allow_failover,
                        'note' => $record->note,
                        'enabled' => $record->enabled,
                    ])
                    ->schema($this->pinSchema())
                    ->action(function (FaxProviderPin $record, array $data): void {
                        Gate::authorize(Capability::SystemAccess->value);

                        $record->update($data);

                        Notification::make()->title('Pin updated.')->success()->send();
                    }),

                Action::make('deletePin')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (FaxProviderPin $record): void {
                        Gate::authorize(Capability::SystemAccess->value);

                        $record->delete();

                        Notification::make()->title('Pin removed.')->success()->send();
                    }),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private function pinSchema(): array
    {
        return [
            Select::make('match_type')
                ->label('Match on')
                ->required()
                ->default(FaxProviderPin::MATCH_NUMBER)
                ->options([
                    FaxProviderPin::MATCH_NUMBER => 'Destination fax number',
                    FaxProviderPin::MATCH_ACCOUNT => 'Intelligent Series account',
                ]),

            TextInput::make('match_value')
                ->label('Value')
                ->required()
                ->maxLength(64)
                // Numbers are normalised on save, so however it is typed here it matches
                // the number the .fs file carries.
                ->helperText('A fax number in any format, or an account number.'),

            Select::make('provider')
                ->required()
                ->options(FaxProvider::options()),

            Toggle::make('allow_failover')
                ->label('May fail over to the other provider')
                ->helperText('Leave off when the pin exists because the other provider is failing for this fax.'),

            TextInput::make('note')
                ->maxLength(255)
                ->helperText('Why this pin exists — the next person will want to know whether it is still needed.'),

            Toggle::make('enabled')->default(true),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.fax-provider-pins');
    }
}
