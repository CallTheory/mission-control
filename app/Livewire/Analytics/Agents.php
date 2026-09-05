<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Models\Stats\Agents\Overview;
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
use Filament\Tables\Table;
use Illuminate\View\View;
use Livewire\Component;

class Agents extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            // The Amtelco stats layer returns the whole result set with no ORDER BY or
            // OFFSET, so searching, sorting and paging are applied in PHP. See StatRecords.
            ->records(fn (int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?string $search) => StatRecords::paginate(
                rows: $this->agentRows(),
                page: $page,
                perPage: $recordsPerPage,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
                search: $search,
                searchable: ['Name', 'Initials', 'agtId'],
            ))
            ->columns([
                TextColumn::make('agtId')->label('Agent ID')->sortable(),

                TextColumn::make('Name')
                    ->label('Name')
                    ->sortable()
                    ->url(fn (array $record): string => '/analytics/agent-performance/'.$record['Name']),

                TextColumn::make('Initials')->label('Initials')->sortable(),

                TextColumn::make('AgentDuration')
                    ->label('Soft Agent Application')
                    ->formatStateUsing(fn ($state): string => Helpers::formatDuration($state))
                    ->sortable(),

                TextColumn::make('SuperDuration')
                    ->label('Supervisor Application')
                    ->formatStateUsing(fn ($state): string => Helpers::formatDuration($state))
                    ->sortable(),

                TextColumn::make('LockedOut')->label('Locked Out')->sortable(),
                TextColumn::make('Calls')->label('Calls')->numeric()->sortable(),
                TextColumn::make('Dispatches')->label('Dispatches')->numeric()->sortable(),
                TextColumn::make('Dials')->label('Dials')->numeric()->sortable(),

                TextColumn::make('DialDur')
                    ->label('Dial Duration')
                    ->formatStateUsing(fn ($state): string => Helpers::formatDuration($state))
                    ->sortable(),
            ])
            ->searchable()
            ->defaultSort('Name')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No agent activity in the last 24 hours.');
    }

    /**
     * @return array<int, object>
     */
    private function agentRows(): array
    {
        try {
            return (new Overview([
                'start_date' => Carbon::now()->subDays(1)->format('Y-m-d H:i:s'),
                'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
            ]))->results;
        } catch (Exception) {
            // An unreachable Amtelco database renders an empty table rather than a 500,
            // which is the behaviour this screen has always had.
            return [];
        }
    }

    public function render(): View
    {
        return view('livewire.analytics.agents');
    }
}
