<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Jobs\SendVoicemailDigest;
use App\Models\Stats\Clients\Overview;
use App\Models\VoicemailDigest as VoicemailDigestModel;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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

class VoicemailDigest extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function delete(VoicemailDigestModel $schedule): void
    {
        $schedule->delete();
        $this->dispatch('saved');
    }

    public function toggleEnabled(VoicemailDigestModel $schedule): void
    {
        $schedule->enabled = ! $schedule->enabled;

        if ($schedule->enabled && ! $schedule->next_run_at) {
            $schedule->next_run_at = $schedule->calculateNextRunAt();
        }

        $schedule->save();
        $this->dispatch('saved');
    }

    public function getTimezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    public function getScheduleTypes(): array
    {
        return [
            'immediate' => 'Immediate',
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

    /**
     * The schedule form, shared by create and edit.
     *
     * @return array<int, mixed>
     */
    private function digestSchema(): array
    {
        return [
            TextInput::make('name')->required()->maxLength(100),

            TextInput::make('client_number')->label('Account')->maxLength(50)
                ->helperText('Leave blank to cover every account the team can see.'),

            TextInput::make('billing_code')->label('Billing Code')->maxLength(50),

            Textarea::make('recipients')->required()->rows(3)->helperText('One address per line.'),

            TextInput::make('subject')->required()->maxLength(255),

            Select::make('schedule_type')
                ->options(fn (): array => $this->getScheduleTypes())
                ->required()
                ->live()
                ->default('immediate'),

            TimePicker::make('schedule_time')
                ->label('Time')
                ->seconds(false)
                ->visible(fn (Get $get): bool => ! in_array($get('schedule_type'), ['immediate', null], true)),

            Select::make('schedule_day_of_week')
                ->label('Day of Week')
                ->options(fn (): array => $this->getDaysOfWeek())
                ->visible(fn (Get $get): bool => $get('schedule_type') === 'weekly'),

            TextInput::make('schedule_day_of_month')
                ->label('Day of Month')->numeric()->minValue(1)->maxValue(31)
                ->visible(fn (Get $get): bool => $get('schedule_type') === 'monthly'),

            Toggle::make('include_transcription')->label('Include transcription'),
            Toggle::make('include_call_metadata')->label('Include call metadata'),

            Select::make('timezone')
                ->options(fn (): array => collect($this->getTimezones())->mapWithKeys(fn (string $tz): array => [$tz => $tz])->all())
                ->searchable()->required()->default('UTC'),
        ];
    }

    /**
     * An immediate digest carries no timing columns, which the isImmediate branches
     * expressed by hand in create() and again in update().
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toAttributes(array $data): array
    {
        $isImmediate = ($data['schedule_type'] ?? 'immediate') === 'immediate';

        return [
            ...$data,
            'client_number' => $data['client_number'] ?: null,
            'billing_code' => $data['billing_code'] ?: null,
            'recipients' => collect(preg_split('/\r\n|\r|\n/', (string) ($data['recipients'] ?? '')))
                ->map(fn (string $line): string => trim($line))->filter()->values()->all(),
            'schedule_time' => $isImmediate ? null : ($data['schedule_time'] ?: null),
            'schedule_day_of_week' => $isImmediate ? null : ($data['schedule_day_of_week'] ?? null),
            'schedule_day_of_month' => $isImmediate ? null : ($data['schedule_day_of_month'] ?? null),
        ];
    }

    public function createDigestAction(): Action
    {
        return Action::make('createDigest')
            ->label('New Digest')
            ->modalHeading('New Voicemail Digest')
            ->schema($this->digestSchema())
            ->action(function (array $data): void {
                VoicemailDigestModel::create([
                    ...$this->toAttributes($data),
                    'team_id' => request()->user()->currentTeam->id,
                    'enabled' => true,
                    'next_run_at' => null,
                ]);

                Notification::make()->title('Digest created.')->success()->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => VoicemailDigestModel::query()
                ->where('team_id', request()->user()->currentTeam->id))
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('client_number')->label('Account')->placeholder('All accounts')->searchable(),
                TextColumn::make('schedule_type')->label('Schedule')->badge()
                    ->formatStateUsing(fn (string $state): string => $this->getScheduleTypes()[$state] ?? $state),
                TextColumn::make('last_run_at')->label('Last Run')->dateTime()->placeholder('Never')->sortable(),
                TextColumn::make('next_run_at')->label('Next Run')->dateTime()->placeholder('—')->sortable(),
                IconColumn::make('enabled')->boolean(),
            ])
            ->headerActions([
                $this->createDigestAction(),
            ])
            ->recordActions([
                Action::make('sendNow')
                    ->label('Send Now')
                    ->link()
                    ->modalHeading('Send Digest Now')
                    ->fillForm(function (VoicemailDigestModel $record): array {
                        [$start, $end] = $record->getDateRange();

                        return ['start_date' => $start, 'end_date' => $end];
                    })
                    ->schema([
                        DateTimePicker::make('start_date')->label('Start')->required()->seconds(false),
                        DateTimePicker::make('end_date')->label('End')->required()->seconds(false)->after('start_date'),
                    ])
                    ->action(function (VoicemailDigestModel $record, array $data): void {
                        SendVoicemailDigest::dispatch(
                            $record,
                            Carbon::parse($data['start_date'], $record->timezone),
                            Carbon::parse($data['end_date'], $record->timezone),
                        );

                        Notification::make()
                            ->title('Voicemail digest job has been queued. Check the Digest History tab for results.')
                            ->success()
                            ->send();
                    }),

                Action::make('edit')
                    ->label('Edit')
                    ->link()
                    ->modalHeading('Edit Voicemail Digest')
                    ->fillForm(fn (VoicemailDigestModel $record): array => [
                        ...$record->only([
                            'name', 'client_number', 'billing_code', 'subject', 'schedule_type',
                            'schedule_time', 'schedule_day_of_week', 'schedule_day_of_month',
                            'include_transcription', 'include_call_metadata', 'timezone',
                        ]),
                        'recipients' => implode("\n", $record->recipients ?? []),
                    ])
                    ->schema($this->digestSchema())
                    ->action(function (VoicemailDigestModel $record, array $data): void {
                        $record->fill($this->toAttributes($data));

                        if ($record->schedule_type !== 'immediate') {
                            $record->next_run_at = $record->calculateNextRunAt();
                        }

                        $record->save();

                        Notification::make()->title('Digest updated.')->success()->send();
                    }),

                Action::make('toggleEnabled')
                    ->label(fn (VoicemailDigestModel $record): string => $record->enabled ? 'Disable' : 'Enable')
                    ->link()
                    ->action(fn (VoicemailDigestModel $record) => $this->toggleEnabled($record)),

                Action::make('delete')
                    ->label('Delete')->link()->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete this digest?')
                    ->action(fn (VoicemailDigestModel $record) => $this->delete($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No voicemail digests yet.');
    }

    public function render(): View
    {
        return view('livewire.utilities.voicemail-digest');
    }
}
