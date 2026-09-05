<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Models\Stats\Agents\Listing;
use App\Models\Stats\Calls\CallLog as CallLogStats;
use App\Models\Stats\Helpers;
use App\Models\Stats\Messages\Keywords;
use App\Models\System\Settings;
use App\Support\Tables\StatRecords;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CallLog extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Call types, keyed by id, for the filter select. */
    public array $ck = [];

    /** Agent listing for the filter select. */
    public array $agents = [];

    /** Message keywords for the filter select. */
    public array $keywords = [];

    /** The generated T-SQL and its bindings, surfaced by the debug panel. */
    public array $sql_params = [];

    public string $sql_code = '';

    /** Switch data timezone; the Amtelco timestamps are recorded in it. */
    public string $timezone = 'UTC';

    public function mount(): void
    {
        try {
            $this->agents = (new Listing)->results;
        } catch (Exception) {
            $this->agents = [];
        }

        try {
            $this->keywords = (new Keywords)->results;
        } catch (Exception) {
            $this->keywords = [];
        }

        $ck = Helpers::callTypes();
        asort($ck);
        $this->ck = $ck;

        $this->timezone = Settings::firstOrFail()->switch_data_timezone ?? 'UTC';
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection, array $filters) {
                // The T-SQL applies every filter and the ORDER BY, so only page here.
                return StatRecords::paginate(
                    rows: $this->callRows($filters, $sortColumn, $sortDirection),
                    page: $page,
                    perPage: $recordsPerPage,
                );
            })
            ->columns([
                TextColumn::make('CallId')
                    ->label('Call ID')
                    ->fontFamily('mono')
                    ->url(fn (array $record): string => '/utilities/call-lookup/'.$record['CallId']),

                TextColumn::make('CallStart')
                    ->label('Call Start')
                    ->formatStateUsing(fn ($state): string => Carbon::parse($state, $this->timezone)
                        ->timezone(Auth::user()->timezone ?? 'UTC')
                        ->format('m/d/Y g:i:s A'))
                    ->sortable(),

                TextColumn::make('CallerANI')
                    ->label('Caller')
                    ->description(fn (array $record): ?string => $record['CallerName'] ?? null),

                TextColumn::make('ClientNumber')
                    ->label('Client')
                    ->description(fn (array $record): ?string => $record['ClientName'] ?? null),

                TextColumn::make('Agents')
                    ->label('Agent(s)')
                    ->placeholder('—'),

                TextColumn::make('CallDuration')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state): string => Helpers::formatDuration($state))
                    ->sortable(),

                TextColumn::make('assets')
                    ->label('Assets')
                    ->badge()
                    ->color('gray')
                    ->state(fn (array $record): array => array_values(array_filter([
                        ! empty($record['hasMessages']) ? 'Messages' : null,
                        ! empty($record['hasRecordings']) ? 'Recordings' : null,
                        ! empty($record['hasVideo']) ? 'Video' : null,
                    ]))),
            ])
            ->filters([
                Filter::make('call')
                    ->schema([
                        DateTimePicker::make('start_date')
                            ->label('Start Date ('.$this->timezone.')')
                            ->seconds(false)
                            ->default(now($this->timezone)->subHour()),
                        DateTimePicker::make('end_date')
                            ->label('End Date ('.$this->timezone.')')
                            ->seconds(false)
                            ->default(now($this->timezone)),
                        TextInput::make('client_number')->label('Client Number'),
                        TextInput::make('ani')->label('ANI'),
                        Select::make('call_type')->label('Call Type')->options($this->ck)->searchable(),
                        Select::make('agent')
                            ->label('Agent')
                            ->options(collect($this->agents)->pluck('Name', 'Name')->all())
                            ->searchable(),
                        TextInput::make('min_duration')->label('Min. Duration (seconds)')->numeric(),
                        TextInput::make('max_duration')->label('Max. Duration (seconds)')->numeric(),
                        Select::make('keyword')
                            ->label('Keyword')
                            ->options(collect($this->keywords)->pluck('Keyword', 'Keyword')->all())
                            ->searchable(),
                        TextInput::make('keyword_search')->label('Keyword Contains'),
                        Checkbox::make('has_messages')->label('Has messages'),
                        Checkbox::make('has_recordings')->label('Has recordings'),
                        Checkbox::make('has_video')->label('Has screen capture'),
                    ])
                    ->columns(3)
                    // Every filter is applied inside the T-SQL rather than over the
                    // returned rows, so there is no query builder to modify here.
                    ->query(fn ($query) => $query),
            ], layout: FiltersLayout::AboveContent)
            // Replaces the hand-rolled Session::put wiring the old filter form did.
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->defaultSort('CallStart', 'desc')
            ->paginated([50, 100, 200])
            ->emptyStateHeading('No calls match these filters.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, object>
     */
    private function callRows(array $filters, ?string $sortColumn, ?string $sortDirection): array
    {
        $f = $filters['call'] ?? [];
        $team = request()->user()->currentTeam;

        $hasMessages = (bool) ($f['has_messages'] ?? false);
        $hasRecordings = (bool) ($f['has_recordings'] ?? false);
        $hasVideo = (bool) ($f['has_video'] ?? false);

        try {
            $callLog = new CallLogStats(
                Carbon::parse($f['start_date'] ?? now($this->timezone)->subHour())->format('Y-m-d H:i:s'),
                Carbon::parse($f['end_date'] ?? now($this->timezone))->format('Y-m-d H:i:s'),
                $this->timezone,
                $f['client_number'] ?? null,
                $f['ani'] ?? null,
                $f['call_type'] ?? null,
                $f['agent'] ?? null,
                $f['min_duration'] ?? null,
                $f['max_duration'] ?? null,
                $f['keyword'] ?? null,
                $f['keyword_search'] ?? null,
                // CallLogStats allow-lists both before interpolating them into its
                // ORDER BY, so an unexpected column falls back rather than injecting.
                $this->sortColumnToSql($sortColumn),
                $sortDirection,
                $hasMessages,
                $hasRecordings,
                $hasVideo,
                // "any" is the absence of a specific asset requirement, which is what
                // the old has_any checkbox meant.
                ! $hasMessages && ! $hasRecordings && ! $hasVideo,
                $team->allowed_accounts,
                $team->allowed_billing,
            );

            $this->sql_code = $callLog->tsql();
            $this->sql_params = $callLog->parameters;

            return $callLog->results ?? [];
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Map a table column name onto the SQL expression the stats query orders by.
     */
    private function sortColumnToSql(?string $column): string
    {
        return match ($column) {
            'CallDuration' => 'CallDuration',
            default => 'statCallStart.Stamp',
        };
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm">
           Loading call log...one moment, please.
        </div>
        HTML;
    }

    public function render(): View
    {
        return view('livewire.analytics.call-log');
    }
}
