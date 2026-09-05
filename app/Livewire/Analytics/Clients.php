<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Models\Stats\Clients\Overview;
use App\Models\Stats\Clients\Sources;
use App\Support\Tables\StatRecords;
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

class Clients extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (int $page, int $recordsPerPage, ?string $sortColumn, ?string $sortDirection, ?string $search) => StatRecords::paginate(
                rows: $this->clientRows(),
                page: $page,
                perPage: $recordsPerPage,
                sortColumn: $sortColumn,
                sortDirection: $sortDirection,
                search: $search,
                searchable: ['ClientNumber', 'ClientName', 'BillingCode'],
            ))
            ->columns([
                TextColumn::make('cltId')->label('Client ID')->sortable(),
                TextColumn::make('ClientNumber')->label('Client Number')->sortable(),
                TextColumn::make('BillingCode')->label('Billing Code')->sortable(),

                TextColumn::make('ClientName')
                    ->label('Client Name')
                    ->sortable()
                    ->url(fn (array $record): string => '/analytics/client-accounts/'.$record['ClientNumber']),

                TextColumn::make('Sources')
                    ->label('Sources')
                    ->badge()
                    ->state(fn (array $record): array => $this->sourcesByClient()[$record['cltId']] ?? []),
            ])
            ->searchable()
            ->defaultSort('ClientNumber')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No records found.');
    }

    /**
     * @return array<int, object>
     */
    private function clientRows(): array
    {
        try {
            return (new Overview([]))->results;
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Sources come back as a second flat result set that has to be matched to clients
     * by cltId. Grouping once per render replaces a nested loop over every source for
     * every row.
     *
     * @return array<int|string, array<int, string>>
     */
    private function sourcesByClient(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        try {
            $rows = (new Sources(['all' => true]))->results;
        } catch (Exception) {
            return $cache = [];
        }

        $grouped = [];

        foreach ($rows as $source) {
            $grouped[$source->cltId][] = $source->Source;
        }

        return $cache = $grouped;
    }

    public function render(): View
    {
        return view('livewire.analytics.clients');
    }
}
