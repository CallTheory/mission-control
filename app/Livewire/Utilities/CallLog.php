<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\FiltersCallLog;
use App\Models\Stats\Helpers;
use App\Support\Tables\StatRecords;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
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
    use FiltersCallLog;
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** The generated T-SQL and its bindings, surfaced by the debug panel. */
    public array $sql_params = [];

    public string $sql_code = '';

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
                    ->schema($this->callLogFilterSchema())
                    ->columns(['default' => 1, 'sm' => 2, 'lg' => 3])
                    // Every filter is applied inside the T-SQL rather than over the
                    // returned rows, so there is no query builder to modify here.
                    ->query(fn ($query) => $query),
            ], layout: FiltersLayout::AboveContent)
            // One filter group, so it gets the whole width. Filament's default grid
            // for AboveContent is 2-5 columns depending on breakpoint, which would
            // squeeze this entire group -- and the grid of fields inside it -- into a
            // single narrow column on a wide screen.
            ->filtersFormColumns(1)
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
        try {
            $callLog = $this->callLogQuery($filters['call'] ?? [], $sortColumn, $sortDirection);

            $this->sql_code = $callLog->tsql();
            $this->sql_params = $callLog->parameters;

            return $callLog->results ?? [];
        } catch (Exception) {
            return [];
        }
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
        return view('livewire.utilities.call-log');
    }
}
