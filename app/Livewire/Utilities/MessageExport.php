<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Jobs\ProcessMessageExport;
use App\Models\MessageExport as MessageExportModel;
use App\Models\Stats\Clients\Overview;
use App\Models\Stats\Messages\AccountFieldDiscovery;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

class MessageExport extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function delete(MessageExportModel $export): void
    {
        $export->delete();
        $this->dispatch('saved');
    }

    public function toggleEnabled(MessageExportModel $export): void
    {
        $export->enabled = ! $export->enabled;

        if ($export->enabled && ! $export->next_run_at && ! $export->isManual()) {
            $export->next_run_at = $export->calculateNextRunAt();
        }

        $export->save();
        $this->dispatch('saved');
    }

    public function getTimezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    public function getScheduleTypes(): array
    {
        return [
            'manual' => 'Manual (On-Demand Only)',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ];
    }

    public function getDaysOfWeek(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }

    public function getClients(): array
    {
        $team = request()->user()->currentTeam;

        try {
            $clients = new Overview([
                'order_by' => 'ClientNumber',
                'order_direction' => 'asc',
                'client_number' => '',
                'client_name' => '',
                'billing_code' => '',
                'allowed_accounts' => $team->allowed_accounts,
                'allowed_billing' => $team->allowed_billing,
                'account_setting' => '',
                'account_setting_value' => '',
                'client_source' => '',
            ]);

            return $clients->results ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function findClientName(string $clientNumber): ?string
    {
        $clients = $this->getClients();
        foreach ($clients as $client) {
            if ((string) $client->ClientNumber === $clientNumber) {
                return $client->ClientName ?? null;
            }
        }

        return null;
    }

    /**
     * The schedule form, shared by create and edit.
     *
     * @return array<int, mixed>
     */
    private function exportSchema(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(100),

            Select::make('client_number')
                ->label('Account')
                ->options(fn (): array => collect($this->getClients())
                    ->mapWithKeys(fn ($c): array => [
                        ($c['ClientNumber'] ?? $c['client_number'] ?? '') => trim(($c['ClientNumber'] ?? $c['client_number'] ?? '').' — '.($c['ClientName'] ?? $c['client_name'] ?? '')),
                    ])->all())
                ->searchable()
                ->required()
                // The available message fields depend on the account, so the checkbox
                // list below has to rebuild whenever this changes. That is what
                // discoverFields() did through an updated hook.
                ->live()
                ->afterStateUpdated(fn (callable $set) => $set('selected_fields', [])),

            CheckboxList::make('selected_fields')
                ->label('Fields to Export')
                ->options(fn (Get $get): array => $this->discoverFieldsFor($get('client_number')))
                ->required()
                ->bulkToggleable()
                ->columns(3)
                ->helperText('Fields are read from the account, so pick the account first.'),

            TextInput::make('filter_field')->label('Filter Field')->helperText('Optional. Restrict the export to messages where this field matches.'),
            TextInput::make('filter_value')->label('Filter Value'),

            Toggle::make('include_call_info')->label('Include call information'),

            Textarea::make('recipients')
                ->required()
                ->rows(3)
                ->helperText('One address per line.'),

            TextInput::make('subject')->required()->maxLength(255),

            Select::make('schedule_type')
                ->options(fn (): array => $this->getScheduleTypes())
                ->required()
                ->live()
                ->default('manual'),

            TimePicker::make('schedule_time')
                ->label('Time')
                ->seconds(false)
                ->visible(fn (Get $get): bool => ! in_array($get('schedule_type'), ['manual', null], true)),

            Select::make('schedule_day_of_week')
                ->label('Day of Week')
                ->options(fn (): array => $this->getDaysOfWeek())
                ->visible(fn (Get $get): bool => $get('schedule_type') === 'weekly'),

            TextInput::make('schedule_day_of_month')
                ->label('Day of Month')
                ->numeric()
                ->minValue(1)
                ->maxValue(31)
                ->visible(fn (Get $get): bool => $get('schedule_type') === 'monthly'),

            Select::make('timezone')
                ->options(fn (): array => collect($this->getTimezones())->mapWithKeys(fn (string $tz): array => [$tz => $tz])->all())
                ->searchable()
                ->required()
                ->default('UTC'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function discoverFieldsFor(?string $clientNumber): array
    {
        if (blank($clientNumber)) {
            return [];
        }

        try {
            $fields = (new AccountFieldDiscovery($clientNumber))->getAvailableFields();
        } catch (Exception) {
            // The account database is Amtelco; unreachable means no fields to offer
            // rather than a broken form.
            return [];
        }

        return collect($fields)->mapWithKeys(fn ($field): array => [$field => $field])->all();
    }

    /**
     * Turn submitted form data into the model's shape. Manual schedules carry no
     * timing columns, which is what the isManual branches expressed by hand.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toAttributes(array $data): array
    {
        $isManual = ($data['schedule_type'] ?? 'manual') === 'manual';

        return [
            ...$data,
            'client_name' => $this->findClientName($data['client_number']),
            'filter_field' => $data['filter_field'] ?: null,
            'filter_value' => $data['filter_value'] ?: null,
            'recipients' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['recipients'] ?? '')))
                ->map(fn (string $line): string => trim($line))
                ->filter()
                ->values()
                ->all(),
            'schedule_time' => $isManual ? null : ($data['schedule_time'] ?: null),
            'schedule_day_of_week' => $isManual ? null : ($data['schedule_day_of_week'] ?? null),
            'schedule_day_of_month' => $isManual ? null : ($data['schedule_day_of_month'] ?? null),
        ];
    }

    public function createExportAction(): Action
    {
        return Action::make('createExport')
            ->label('New Export')
            ->modalHeading('New Message Export')
            ->schema($this->exportSchema())
            ->action(function (array $data): void {
                $export = new MessageExportModel([
                    ...$this->toAttributes($data),
                    'team_id' => request()->user()->currentTeam->id,
                    'enabled' => true,
                ]);

                if ($export->schedule_type !== 'manual') {
                    $export->next_run_at = $export->calculateNextRunAt();
                }

                $export->save();

                Notification::make()->title('Export created.')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => MessageExportModel::query()
                ->where('team_id', request()->user()->currentTeam->id))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),

                TextColumn::make('client_number')
                    ->label('Account')
                    ->description(fn (MessageExportModel $record): ?string => $record->client_name)
                    ->searchable(),

                TextColumn::make('selected_fields')
                    ->label('Fields')
                    ->state(fn (MessageExportModel $record): string => count($record->selected_fields ?? []).' selected'),

                TextColumn::make('schedule_type')
                    ->label('Schedule')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->getScheduleTypes()[$state] ?? $state),

                TextColumn::make('last_run_at')->label('Last Run')->dateTime()->placeholder('Never')->sortable(),

                IconColumn::make('enabled')->boolean(),
            ])
            ->headerActions([
                $this->createExportAction(),
            ])
            ->recordActions([
                Action::make('runNow')
                    ->label('Run Now')
                    ->link()
                    ->modalHeading('Run Export Now')
                    ->fillForm(function (MessageExportModel $record): array {
                        [$start, $end] = $record->getDateRange();

                        return ['start_date' => $start, 'end_date' => $end];
                    })
                    ->schema([
                        DateTimePicker::make('start_date')->label('Start')->required()->seconds(false),
                        DateTimePicker::make('end_date')->label('End')->required()->seconds(false)->after('start_date'),
                    ])
                    ->action(function (MessageExportModel $record, array $data): void {
                        ProcessMessageExport::dispatch(
                            $record,
                            Carbon::parse($data['start_date'], $record->timezone),
                            Carbon::parse($data['end_date'], $record->timezone),
                            request()->user()->id,
                        );

                        Notification::make()
                            ->title('Message export job has been queued. Check the Export History tab for results.')
                            ->success()
                            ->send();
                    }),

                Action::make('edit')
                    ->label('Edit')
                    ->link()
                    ->modalHeading('Edit Message Export')
                    ->fillForm(fn (MessageExportModel $record): array => [
                        ...$record->only([
                            'name', 'client_number', 'selected_fields', 'filter_field', 'filter_value',
                            'include_call_info', 'subject', 'schedule_type', 'schedule_time',
                            'schedule_day_of_week', 'schedule_day_of_month', 'timezone',
                        ]),
                        'recipients' => implode("\n", $record->recipients ?? []),
                    ])
                    ->schema($this->exportSchema())
                    ->action(function (MessageExportModel $record, array $data): void {
                        $record->fill($this->toAttributes($data));

                        if ($record->schedule_type !== 'manual') {
                            $record->next_run_at = $record->calculateNextRunAt();
                        }

                        $record->save();

                        Notification::make()->title('Export updated.')->success()->send();
                    }),

                Action::make('toggleEnabled')
                    ->label(fn (MessageExportModel $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->link()
                    ->action(fn (MessageExportModel $record) => $this->toggleEnabled($record)),

                Action::make('delete')
                    ->label('Delete')
                    ->link()
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this export?')
                    ->action(fn (MessageExportModel $record) => $this->delete($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No message exports yet.');
    }

    public function render(): View
    {
        return view('livewire.utilities.message-export');
    }
}
