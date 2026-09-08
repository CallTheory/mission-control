<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\AuthorizesWctpManagement;
use App\Models\EnterpriseHost;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;

class EnterpriseHostManagement extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesWctpManagement;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function mount()
    {
        $this->authorizeWctpManagement();
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
                // senderID identifies the host to the carrier, so it has to be unique
                // across every host, not just this team's.
                ->unique('enterprise_hosts', 'senderID', ignoreRecord: true),

            TextInput::make('securityCode')
                ->label('Security Code')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(! $isEdit)
                ->helperText($isEdit
                    ? 'Leave blank to keep the stored code.'
                    : 'At least 8 characters.')
                ->suffixAction(
                    Action::make('generate')
                        ->icon('heroicon-m-sparkles')
                        ->label('Generate')
                        ->action(fn (Set $set) => $set('securityCode', Str::random(16))),
                ),

            TextInput::make('callback_url')->label('Callback URL')->url()->maxLength(255),

            TagsInput::make('phone_numbers')
                ->label('Phone Numbers')
                ->placeholder('+15551234567')
                ->helperText('Entered numbers are normalised to E.164.')
                ->nestedRecursiveRules(['string', 'regex:/^[\\+]?[1-9]\\d{1,14}$/']),

            Toggle::make('enabled')->default(true),
        ];
    }

    private function normalisePhoneNumbers(array $numbers): array
    {
        return array_values(array_unique(array_map(function (string $number): string {
            $digits = preg_replace('/\D+/', '', $number);

            // A bare 10-digit number is North American; prepend the country code.
            if (! str_starts_with($digits, '1') && strlen($digits) === 10) {
                $digits = '1'.$digits;
            }

            return '+'.$digits;
        }, $numbers)));
    }

    public function createHostAction(): Action
    {
        return Action::make('createHost')
            ->label('Create Enterprise Host')
            ->modalHeading('Create Enterprise Host')
            ->schema($this->hostSchema(isEdit: false))
            ->action(function (array $data): void {
                $this->authorizeWctpManagement();

                EnterpriseHost::create([
                    ...$data,
                    'phone_numbers' => $this->normalisePhoneNumbers($data['phone_numbers'] ?? []),
                    // Ownership is always the acting team -- never a client-supplied id.
                    'team_id' => $this->currentTeamId(),
                ]);

                Notification::make()->title('Enterprise Host created successfully.')->success()->send();
            });
    }

    public function editHostAction(): Action
    {
        return Action::make('edit')
            ->label('Edit')
            ->link()
            ->modalHeading('Edit Enterprise Host')
            ->fillForm(fn (EnterpriseHost $record): array => [
                'name' => $record->name,
                'senderID' => $record->senderID,
                // Never prefilled: it would put the stored code in the DOM.
                'securityCode' => '',
                'callback_url' => $record->callback_url,
                'phone_numbers' => $record->phone_numbers ?? [],
                'enabled' => $record->enabled,
            ])
            ->schema($this->hostSchema(isEdit: true))
            ->action(function (EnterpriseHost $record, array $data): void {
                $this->authorizeHost($record);

                $attributes = [
                    ...$data,
                    'phone_numbers' => $this->normalisePhoneNumbers($data['phone_numbers'] ?? []),
                    'team_id' => $this->currentTeamId(),
                ];

                // Blank means "keep the stored code", as the field says.
                if (blank($attributes['securityCode'])) {
                    unset($attributes['securityCode']);
                }

                $record->update($attributes);

                Notification::make()->title('Enterprise Host updated successfully.')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            // Hosts are always scoped to the acting team; the client cannot widen this.
            ->query(fn (): Builder => EnterpriseHost::query()
                ->where('team_id', $this->currentTeamId())
                ->withCount('messages'))
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
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

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

                TextColumn::make('enabled')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled'),
            ])
            ->headerActions([
                $this->createHostAction(),
            ])
            ->recordActions([
                $this->editHostAction(),

                Action::make('toggleEnabled')
                    ->label(fn (EnterpriseHost $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->link()
                    ->action(fn (EnterpriseHost $record) => $this->toggleEnabled($record)),

                Action::make('messages')
                    ->label('Messages')
                    ->link()
                    ->url(fn (EnterpriseHost $record): string => route('utilities.wctp-messages', ['host' => $record->id])),

                Action::make('delete')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this enterprise host?')
                    // A host with messages cannot be deleted, only disabled.
                    ->visible(fn (EnterpriseHost $record): bool => $record->messages_count === 0)
                    ->action(fn (EnterpriseHost $record) => $this->deleteHost($record)),
            ])
            ->defaultSort('name')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No enterprise hosts found.');
    }

    public function render()
    {
        $this->authorizeWctpManagement();

        return view('livewire.utilities.enterprise-host-management');
    }

    public function deleteHost(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        if ($host->messages()->exists()) {
            Notification::make()
                ->title('Cannot delete host with existing messages. Disable it instead.')
                ->danger()
                ->send();

            return;
        }

        $host->delete();

        Notification::make()
            ->title('Enterprise Host deleted successfully.')
            ->success()
            ->send();
    }

    public function toggleEnabled(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        $host->update(['enabled' => ! $host->enabled]);

        $status = $host->enabled ? 'enabled' : 'disabled';
        Notification::make()
            ->title("Enterprise Host {$status} successfully.")
            ->success()
            ->send();
    }

    public function viewMessages(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        return redirect()->route('utilities.wctp-messages', ['host' => $host->id]);
    }

    /**
     * Ensure the bound host belongs to the acting team before any mutation or
     * navigation. Guards against tampered route-model-bound ids.
     */
    protected function authorizeHost(EnterpriseHost $host): void
    {
        $this->authorizeWctpManagement();

        if ((int) $host->team_id !== $this->currentTeamId()) {
            abort(403);
        }
    }
}
