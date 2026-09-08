<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Jobs\SendVoicemailDigest;
use App\Models\VoicemailDigest;
use App\Models\VoicemailDigestLog;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
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

class VoicemailDigestHistory extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var array<string, string> */
    private const STATUSES = [
        'queued' => 'Queued',
        'sent' => 'Sent',
        'failed' => 'Failed',
        'no_recordings' => 'No Recordings',
    ];

    /** @var array<string, string> */
    private const STATUS_COLORS = [
        'queued' => 'warning',
        'sent' => 'success',
        'failed' => 'danger',
        'no_recordings' => 'gray',
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => VoicemailDigestLog::forTeam(request()->user()->currentTeam->id)
                ->with('voicemailDigest'))
            ->columns([
                TextColumn::make('voicemailDigest.name')
                    ->label('Schedule')
                    ->default('—')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date_range')
                    ->label('Date Range')
                    ->color('gray')
                    ->state(fn (VoicemailDigestLog $record): string => Carbon::parse($record->start_date)->format('M j, Y g:ia')
                        .' — '.Carbon::parse($record->end_date)->format('M j, Y g:ia')),

                TextColumn::make('recipients')
                    ->label('Recipients')
                    ->color('gray')
                    ->state(function (VoicemailDigestLog $record): string {
                        $count = count($record->recipients ?? []);

                        return $count.' recipient'.($count === 1 ? '' : 's');
                    })
                    ->tooltip(fn (VoicemailDigestLog $record): string => implode(', ', $record->recipients ?? [])),

                TextColumn::make('recording_count')
                    ->label('Recordings')
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => self::STATUS_COLORS[$state] ?? 'gray')
                    ->tooltip(fn (VoicemailDigestLog $record): ?string => $record->error_message)
                    ->sortable(),

                TextColumn::make('sent_at')
                    ->label('Sent At')
                    ->dateTime('M j, Y g:ia')
                    ->placeholder('—')
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(self::STATUSES)
                    ->placeholder('All Statuses'),

                SelectFilter::make('voicemail_digest_id')
                    ->label('Schedule')
                    ->placeholder('All Schedules')
                    ->options(fn (): array => VoicemailDigest::where('team_id', request()->user()->currentTeam->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
            ])
            ->recordActions([
                Action::make('resend')
                    ->label('Resend')
                    ->link()
                    ->requiresConfirmation()
                    ->modalHeading('Resend this digest?')
                    // A log outlives its schedule, and resending needs the schedule's
                    // timezone and recipients, so hide the action once it is gone.
                    ->visible(fn (VoicemailDigestLog $record): bool => $record->voicemailDigest !== null)
                    ->action(fn (VoicemailDigestLog $record) => $this->resend($record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No digest history found.');
    }

    public function resend(VoicemailDigestLog $log): void
    {
        $digest = $log->voicemailDigest;

        if (! $digest) {
            Notification::make()
                ->title('The parent schedule no longer exists.')
                ->danger()
                ->send();

            return;
        }

        SendVoicemailDigest::dispatch(
            $digest,
            Carbon::parse($log->start_date, $digest->timezone),
            Carbon::parse($log->end_date, $digest->timezone),
        );

        Notification::make()
            ->title('Voicemail digest has been queued for resend.')
            ->success()
            ->send();
    }

    public function render(): View
    {
        return view('livewire.utilities.voicemail-digest-history');
    }
}
