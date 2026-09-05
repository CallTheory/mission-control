<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\MessageExport;
use App\Models\MessageExportLog;
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

class MessageExportHistory extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var array<string, string> */
    private const STATUSES = [
        'queued' => 'Queued',
        'completed' => 'Completed',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'no_messages' => 'No Messages',
    ];

    /** @var array<string, string> */
    private const STATUS_COLORS = [
        'queued' => 'warning',
        'completed' => 'success',
        'sent' => 'info',
        'failed' => 'danger',
        'no_messages' => 'gray',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => MessageExportLog::forTeam(request()->user()->currentTeam->id)
                ->with(['messageExport', 'user']))
            ->columns([
                TextColumn::make('export_name')
                    ->label('Export')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('client_number')
                    ->label('Account')
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('date_range')
                    ->label('Date Range')
                    ->color('gray')
                    ->state(fn (MessageExportLog $record): string => $record->start_date->format('M j, Y g:ia')
                        .' — '.$record->end_date->format('M j, Y g:ia')),

                TextColumn::make('message_count')
                    ->label('Messages')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    ->tooltip(fn (MessageExportLog $record): ?string => $record->error_message)
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Run By')
                    // A log with no user was produced by the schedule, not a person.
                    ->default('Scheduled')
                    ->color('gray'),

                TextColumn::make('created_at')
                    ->label('Run At')
                    ->dateTime('M j, Y g:ia')
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES)
                    ->placeholder('All Statuses'),

                SelectFilter::make('message_export_id')
                    ->label('Export')
                    ->placeholder('All Exports')
                    ->options(fn (): array => MessageExport::where('team_id', request()->user()->currentTeam->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download CSV')
                    ->link()
                    ->url(fn (MessageExportLog $record): string => route('utilities.message-export.download', $record))
                    ->visible(fn (MessageExportLog $record): bool => filled($record->file_path)
                        && $record->status === 'completed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No export history found.');
    }

    public function render(): View
    {
        return view('livewire.utilities.message-export-history');
    }
}
