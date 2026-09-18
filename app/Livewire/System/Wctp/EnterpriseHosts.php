<?php

declare(strict_types=1);

namespace App\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesWctpSection;
use App\Livewire\Concerns\ManagesHostPhoneNumbers;
use App\Models\EnterpriseHost;
use App\Support\WctpSectionAccess;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Enterprise host management — the single screen, replacing the two that used to
 * exist (a team-scoped Utilities one and a System one that also showed global
 * hosts). Hosts are installation-wide now, so there is nothing left to scope and no
 * reason for two screens.
 */
class EnterpriseHosts extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesWctpSection;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ManagesHostPhoneNumbers;

    protected function wctpCapability(): Capability
    {
        return Capability::WctpManage;
    }

    public function mount(): void
    {
        $this->authorizeWctpSection();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => EnterpriseHost::query()->withCount('messages'))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(function (EnterpriseHost $record): ?string {
                        $count = count($record->phone_numbers ?? []);

                        return $count > 0 ? $count.' number'.($count === 1 ? '' : 's') : null;
                    }),

                TextColumn::make('senderID')
                    ->label('Sender ID')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone_numbers')
                    ->label('Numbers')
                    ->badge()
                    // Each number labelled with the carrier that carries it, since
                    // that is what an operator is checking when they look here.
                    ->state(fn (EnterpriseHost $record): array => array_map(
                        fn (string $number): string => $number.' · '.(
                            $record->providerForNumber($number)?->label() ?? $this->defaultCarrierLabel()
                        ),
                        array_values($record->phone_numbers ?? []),
                    )),

                TextColumn::make('messages_count')
                    ->label('Messages')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('last_message_at')
                    ->label('Last Activity')
                    ->since()
                    ->placeholder('Never')
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('enabled')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),
            ])
            ->headerActions([
                Action::make('addHost')
                    ->label('Add Enterprise Host')
                    ->modalHeading('Add Enterprise Host')
                    ->schema($this->hostSchema(isEdit: false))
                    ->action(function (array $data): void {
                        $this->authorizeWctpSection();

                        EnterpriseHost::create([
                            ...$data,
                            ...$this->phoneNumberAttributes($data['phone_numbers'] ?? []),
                            'callback_url' => $data['callback_url'] ?: null,
                        ]);

                        Notification::make()->title('Enterprise Host added successfully.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->link()
                    ->modalHeading('Edit Enterprise Host')
                    ->fillForm(fn (EnterpriseHost $record): array => [
                        'name' => $record->name,
                        'senderID' => $record->senderID,
                        // Never prefilled: it would put the stored code in the DOM.
                        'securityCode' => '',
                        'callback_url' => $record->callback_url,
                        'phone_numbers' => $this->phoneNumberRows($record),
                        'enabled' => $record->enabled,
                    ])
                    ->schema($this->hostSchema(isEdit: true))
                    ->action(function (EnterpriseHost $record, array $data): void {
                        $this->authorizeWctpSection();

                        $attributes = [
                            ...$data,
                            ...$this->phoneNumberAttributes($data['phone_numbers'] ?? []),
                            'callback_url' => $data['callback_url'] ?: null,
                        ];

                        // Blank means "keep the stored code", as the field says.
                        if (blank($attributes['securityCode'] ?? null)) {
                            unset($attributes['securityCode']);
                        }

                        $record->update($attributes);

                        Notification::make()->title('Enterprise Host updated successfully.')->success()->send();
                    }),

                Action::make('toggleEnabled')
                    ->label(fn (EnterpriseHost $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->link()
                    ->action(function (EnterpriseHost $record): void {
                        $this->authorizeWctpSection();

                        $record->update(['enabled' => ! $record->enabled]);

                        Notification::make()
                            ->title("Enterprise Host {$record->name} ".($record->enabled ? 'enabled' : 'disabled').'.')
                            ->success()
                            ->send();
                    }),

                Action::make('messages')
                    ->label('Messages')
                    ->link()
                    // Only useful to someone who can open the log.
                    ->visible(fn (): bool => WctpSectionAccess::allows(Capability::WctpMessages))
                    ->url(fn (EnterpriseHost $record): string => route('system.wctp.messages', ['host' => $record->id])),

                Action::make('delete')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this enterprise host?')
                    // A host with messages is disabled, never deleted.
                    ->visible(fn (EnterpriseHost $record): bool => $record->messages_count === 0)
                    ->action(function (EnterpriseHost $record): void {
                        $this->authorizeWctpSection();

                        if ($record->messages()->exists()) {
                            Notification::make()
                                ->title('Cannot delete host with existing messages. Disable it instead.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $name = $record->name;
                        $record->delete();

                        Notification::make()->title("Enterprise Host '{$name}' removed.")->success()->send();
                    }),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No enterprise hosts configured.');
    }

    /**
     * @return array<int, mixed>
     */
    private function hostSchema(bool $isEdit): array
    {
        return [
            TextInput::make('name')->required()->maxLength(255),

            TextInput::make('senderID')
                ->label('Sender ID')
                ->required()
                ->maxLength(255)
                // senderID identifies the host to the gateway, so it has to be unique.
                ->unique('enterprise_hosts', 'senderID', ignoreRecord: true),

            TextInput::make('securityCode')
                ->label('Security Code')
                ->password()
                ->revealable()
                ->minLength(8)
                ->maxLength(255)
                ->required(! $isEdit)
                ->helperText($isEdit ? 'Leave blank to keep the stored code.' : 'At least 8 characters.')
                ->suffixAction(
                    Action::make('generate')
                        ->icon('heroicon-m-sparkles')
                        ->label('Generate')
                        ->action(fn (Set $set) => $set('securityCode', Str::random(16))),
                ),

            TextInput::make('callback_url')->label('Callback URL')->url()->maxLength(255),

            $this->phoneNumbersField(),

            Toggle::make('enabled')->default(true),
        ];
    }

    public function render(): View
    {
        $this->authorizeWctpSection();

        return view('livewire.system.wctp.enterprise-hosts');
    }
}
