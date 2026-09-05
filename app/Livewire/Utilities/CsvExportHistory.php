<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\CsvExportLog;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

class CsvExportHistory extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /**
     * Keys that are always present on an export and so say nothing about how
     * narrowly it was filtered.
     */
    private const NON_FILTER_KEYS = ['start_date', 'end_date', 'sort_by', 'sort_direction', 'has_any'];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => CsvExportLog::forTeam(request()->user()->currentTeam->id)->with('user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->default('Deleted User')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date_range')
                    ->label('Date Range')
                    ->color('gray')
                    ->state(function (CsvExportLog $record): string {
                        $filters = $record->filters ?? [];

                        if (empty($filters['start_date']) || empty($filters['end_date'])) {
                            return '—';
                        }

                        return Carbon::parse($filters['start_date'])->format('M j, Y g:ia')
                            .' — '.Carbon::parse($filters['end_date'])->format('M j, Y g:ia');
                    }),

                TextColumn::make('active_filters')
                    ->label('Active Filters')
                    ->color('gray')
                    ->state(function (CsvExportLog $record): string {
                        $count = collect($record->filters ?? [])
                            ->except(self::NON_FILTER_KEYS)
                            ->filter(fn ($value): bool => ! is_null($value) && $value !== '' && $value !== false)
                            ->count();

                        return $count.' filter'.($count === 1 ? '' : 's');
                    }),

                TextColumn::make('result_count')
                    ->label('Records')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'completed' ? 'success' : 'danger')
                    ->tooltip(fn (CsvExportLog $record): ?string => $record->error_message)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Exported At')
                    ->dateTime('M j, Y g:ia')
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(['completed' => 'Completed', 'failed' => 'Failed'])
                    ->placeholder('All Statuses'),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->placeholder('All Users')
                    ->options(fn (): array => User::whereIn(
                        'id',
                        CsvExportLog::forTeam(request()->user()->currentTeam->id)->select('user_id')->distinct()
                    )->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('reexport')
                    ->label('Re-export')
                    ->link()
                    ->action(fn (CsvExportLog $record) => $this->reexport($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No export history found.');
    }

    public function reexport(CsvExportLog $log): void
    {
        $team = request()->user()->currentTeam;

        // The table query is already team-scoped; this keeps the guard on the action
        // itself, which is what an id posted straight to /livewire/update would hit.
        if ((int) $log->team_id !== (int) $team->id) {
            session()->flash('error', 'Export log not found.');

            return;
        }

        $this->redirect(route('utilities.csv-export', ['reexport_log_id' => $log->id]));
    }

    public function render(): View
    {
        return view('livewire.utilities.csv-export-history');
    }
}
