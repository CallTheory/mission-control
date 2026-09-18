<?php

declare(strict_types=1);

namespace App\Livewire\System\AzureTokens;

use App\Enums\AzureCredentialSource;
use App\Enums\AzureCredentialStatus;
use App\Enums\AzureCredentialType;
use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\AzureCredential;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Every client secret and certificate in the tenant, soonest expiry first.
 *
 * Expired credentials are shown rather than hidden: Azure never deletes them, so
 * they are both the immediate problem and the cleanup list. What is hidden by
 * default is credentials the last sweep no longer saw -- those are gone from Azure
 * and only kept here as history.
 */
class Credentials extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesSystemComponent;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAzureTokens;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => AzureCredential::query())
            ->defaultSort('end_utc', 'asc')
            ->columns([
                TextColumn::make('app_name')
                    ->label('App')
                    ->searchable(['app_name', 'app_client_id'])
                    ->sortable()
                    ->wrap()
                    ->description(fn (AzureCredential $record): string => $record->source->label()),

                TextColumn::make('cred_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->state(fn (AzureCredential $record): string => $record->cred_type->label())
                    ->sortable(),

                TextColumn::make('cred_name')
                    ->label('Credential')
                    // Blank names are the norm in Azure, so this falls back to the
                    // secret hint or the tail of the keyId.
                    ->state(fn (AzureCredential $record): string => $record->label())
                    ->description(fn (AzureCredential $record): ?string => $record->acknowledged
                        ? 'Acknowledged'.($record->acknowledgedBy ? ' by '.$record->acknowledgedBy->name : '')
                        : null)
                    ->searchable(['cred_name', 'hint', 'key_id'])
                    ->wrap(),

                TextColumn::make('end_utc')
                    ->label('Expires')
                    ->dateTime('Y-m-d H:i')
                    ->description(fn (AzureCredential $record): string => $record->end_utc->diffForHumans())
                    ->sortable(),

                TextColumn::make('days_left')
                    ->label('Days left')
                    ->badge()
                    ->alignEnd()
                    ->state(fn (AzureCredential $record): string => $record->hasExpired()
                        ? 'expired'
                        : (string) $record->daysRemaining())
                    ->color(fn (AzureCredential $record): string => $record->status()->color())
                    // There is no days column to sort on; the expiry date is the
                    // same ordering.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('end_utc', $direction)),

                TextColumn::make('status')
                    ->badge()
                    ->state(fn (AzureCredential $record): string => $record->status()->label())
                    ->color(fn (AzureCredential $record): string => $record->status()->color()),

                TextColumn::make('app_client_id')
                    ->label('Client ID')
                    ->fontFamily('mono')
                    ->copyable()
                    ->copyMessage('Client ID copied')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('last_seen_utc')
                    ->label('Last seen')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->placeholder('All Statuses')
                    ->options(AzureCredentialStatus::options())
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<AzureCredential> $query */
                        return blank($data['value'] ?? null)
                            ? $query
                            : $query->status(AzureCredentialStatus::from($data['value']));
                    }),

                SelectFilter::make('cred_type')
                    ->label('Type')
                    ->placeholder('All Types')
                    ->options(AzureCredentialType::options()),

                SelectFilter::make('source')
                    ->label('Object')
                    ->placeholder('Apps and service principals')
                    ->options(AzureCredentialSource::options()),

                SelectFilter::make('presence')
                    ->label('Presence')
                    ->selectablePlaceholder(false)
                    ->default('present')
                    ->options([
                        'present' => 'Present in Azure',
                        'removed' => 'Removed from Azure',
                        'all' => 'Both',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<AzureCredential> $query */
                        return match ($data['value'] ?? 'present') {
                            'removed' => $query->removed(),
                            'all' => $query,
                            default => $query->present(),
                        };
                    }),

                TernaryFilter::make('acknowledged')
                    ->label('Acknowledged')
                    ->placeholder('Any'),
            ])
            ->recordActions([
                Action::make('acknowledge')
                    ->label('Acknowledge')
                    ->link()
                    ->requiresConfirmation()
                    ->modalHeading('Acknowledge this credential')
                    ->modalDescription('No further expiry alerts will be sent for it. It stays on '
                        .'this dashboard with its real status.')
                    ->visible(fn (AzureCredential $record): bool => ! $record->acknowledged)
                    ->action(function (AzureCredential $record): void {
                        $this->authorize($this->requiredCapability()->value);

                        $record->update([
                            'acknowledged' => true,
                            'acknowledged_at' => Carbon::now(),
                            'acknowledged_by' => auth()->id(),
                        ]);

                        $this->dispatch('azure-credentials-updated');

                        Notification::make()->title('Credential acknowledged')->success()->send();
                    }),

                Action::make('unacknowledge')
                    ->label('Un-acknowledge')
                    ->link()
                    ->visible(fn (AzureCredential $record): bool => $record->acknowledged)
                    ->action(function (AzureCredential $record): void {
                        $this->authorize($this->requiredCapability()->value);

                        // The alert history is cleared as well, so a credential that
                        // is in the warning window right now alerts again rather than
                        // staying silent because it was muted when it crossed.
                        $record->update([
                            'acknowledged' => false,
                            'acknowledged_at' => null,
                            'acknowledged_by' => null,
                            'alerted_threshold' => null,
                        ]);

                        $this->dispatch('azure-credentials-updated');

                        Notification::make()->title('Alerts re-enabled')->success()->send();
                    }),

                Action::make('portal')
                    ->label('Entra portal')
                    ->link()
                    ->url(fn (AzureCredential $record): string => $record->portalUrl())
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('No credentials recorded')
            ->emptyStateDescription('Configure Entra ID on System -> Integrations, then run a sweep.');
    }

    public function render(): View
    {
        return view('livewire.system.azure-tokens.credentials');
    }
}
