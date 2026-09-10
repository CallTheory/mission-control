<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\DataSource;
use App\Models\EnterpriseHost;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;

class WctpGateway extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesSystemComponent;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAccess;
    }

    // Enterprise Hosts
    public Collection $enterpriseHosts;

    // New Enterprise Host form

    // Phone Number Management for hosts

    // UI State
    public string $activeTab = 'overview';

    public bool $twilioConfigured = false;

    public string $wctpEndpoint = '';

    public function mount(): void
    {
        // Check if Twilio is configured. DataSource is a single-row singleton;
        // providers are column prefixes on that row, not typed rows.
        $dataSource = DataSource::first();

        $this->twilioConfigured = $dataSource
            && ! empty($dataSource->twilio_account_sid)
            && ! empty($dataSource->twilio_auth_token)
            && ! empty($dataSource->twilio_from_number);

        // Set the WCTP endpoint URL
        $this->wctpEndpoint = url('/wctp');

    }

    protected function findHost(int $hostId): ?EnterpriseHost
    {
        $teamId = auth()->user()->currentTeam->id ?? null;

        $query = EnterpriseHost::where('id', $hostId);

        if ($teamId) {
            $query->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)
                    ->orWhereNull('team_id');
            });
        }

        return $query->first();
    }

    public function table(Table $table): Table
    {
        $teamId = auth()->user()->currentTeam->id ?? null;

        return $table
            // Unlike the team-scoped Utilities screen, this one also shows global
            // hosts (team_id null), which is the reason both screens exist.
            ->query(fn (): Builder => EnterpriseHost::query()
                ->when($teamId, fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner->where('team_id', $teamId)->orWhereNull('team_id')
                ))
                ->withCount('messages'))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('senderID')->label('Sender ID')->fontFamily('mono')->searchable()->copyable(),

                TextColumn::make('team.name')
                    ->label('Team')
                    ->placeholder('Global')
                    ->description(fn (EnterpriseHost $record): ?string => $record->team_id === null
                        ? 'Visible to every team'
                        : null),

                TextColumn::make('phone_numbers')
                    ->label('Numbers')
                    ->badge()
                    ->state(fn (EnterpriseHost $record): array => $record->phone_numbers ?? []),

                TextColumn::make('messages_count')->label('Messages')->numeric()->sortable(),

                IconColumn::make('enabled')->boolean(),
            ])
            ->headerActions([
                Action::make('addHost')
                    ->label('Add Enterprise Host')
                    ->modalHeading('Add Enterprise Host')
                    ->schema($this->hostSchema(isEdit: false))
                    ->action(function (array $data): void {
                        EnterpriseHost::create([
                            ...$data,
                            'callback_url' => $data['callback_url'] ?: null,
                            'phone_numbers' => $data['phone_numbers'] ?? [],
                            'team_id' => auth()->user()->currentTeam->id ?? null,
                            'enabled' => true,
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
                        'securityCode' => '',
                        'callback_url' => $record->callback_url,
                        'phone_numbers' => $record->phone_numbers ?? [],
                    ])
                    ->schema($this->hostSchema(isEdit: true))
                    ->action(function (EnterpriseHost $record, array $data): void {
                        if (blank($data['securityCode'])) {
                            unset($data['securityCode']);
                        }

                        $record->update([
                            ...$data,
                            'callback_url' => $data['callback_url'] ?: null,
                            'phone_numbers' => $data['phone_numbers'] ?? [],
                        ]);

                        Notification::make()->title('Enterprise Host updated successfully.')->success()->send();
                    }),

                Action::make('toggleEnabled')
                    ->label(fn (EnterpriseHost $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->link()
                    ->action(function (EnterpriseHost $record): void {
                        $record->update(['enabled' => ! $record->enabled]);

                        Notification::make()
                            ->title("Enterprise Host {$record->name} ".($record->enabled ? 'enabled' : 'disabled').'.')
                            ->success()
                            ->send();
                    }),

                Action::make('delete')
                    ->label('Delete')->link()->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this enterprise host?')
                    // A host with messages is disabled, never deleted.
                    ->visible(fn (EnterpriseHost $record): bool => $record->messages_count === 0)
                    ->action(function (EnterpriseHost $record): void {
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
                        ->action(fn (Set $set) => $set('securityCode', Str::random(16))),
                ),

            TextInput::make('callback_url')->label('Callback URL')->url()->maxLength(255),

            TagsInput::make('phone_numbers')->label('Phone Numbers')->placeholder('+15551234567'),
        ];
    }

    public function render(): View
    {
        return view('livewire.system.wctp-gateway');
    }
}
