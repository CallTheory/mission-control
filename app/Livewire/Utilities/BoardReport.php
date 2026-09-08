<?php

namespace App\Livewire\Utilities;

use App\Enums\Capability;
use App\Jobs\ExportBoardCheckForPeoplePraise;
use App\Jobs\PeoplePraiseApi\ExportBoardCheckForPeoplePraiseApi;
use App\Livewire\Concerns\AuthorizesBoardComponent;
use App\Livewire\Concerns\ReviewsBoardCheckItems;
use App\Models\BoardCheckItem;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class BoardReport extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesBoardComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::BoardReport;
    }

    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ReviewsBoardCheckItems;

    public $currentUrl;

    public $listeners = ['boardCheckSupervisorItemUpdated' => 'render'];

    public function exportPeopleSoft(): void
    {
        $method = Settings::first()?->board_check_people_praise_export_method;

        if ($method === 'file') {
            ExportBoardCheckForPeoplePraise::dispatch();
            BoardCheckActivity::create([
                'activity_type' => 'Exported to file',
                'user_id' => Auth::user()->id,
            ]);
        } elseif ($method === 'api') {
            ExportBoardCheckForPeoplePraiseApi::dispatch();
            BoardCheckActivity::create([
                'activity_type' => 'Exported to People Praise API',
                'user_id' => Auth::user()->id,
            ]);
        } else {
            BoardCheckActivity::create([
                'activity_type' => 'Export Failed: Expected "file" or "api" but got '.($method ?? 'no configured method'),
                'user_id' => Auth::user()->id,
            ]);
        }

        $this->dispatch('saved');
        $this->dispatch('boardCheckSupervisorItemUpdated');
    }

    public function mount(): void
    {
        $this->currentUrl = url()->current();
    }

    protected function acceptOutcome(): array
    {
        return ['label' => 'Mark OK', 'activity' => 'Supervisor Marked OK'];
    }

    protected function escalateOutcome(): array
    {
        return ['label' => 'Confirm Problem', 'activity' => 'Supervisor Confirmed Problem'];
    }

    protected function acceptColumns(): array
    {
        return ['marked_ok'];
    }

    protected function escalateColumns(): array
    {
        return ['problem_verified'];
    }

    /**
     * The supervisor is verifying a problem a dispatcher already described, so the
     * category and comments are not re-collected here.
     */
    protected function escalateCollectsDetail(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $categories = Helpers::boardCheckCategories();

        return $table
            // Items a supervisor has resolved, either way.
            ->query(fn (): Builder => BoardCheckItem::query()
                ->where(fn (Builder $q) => $q->whereNotNull('problem_verified_at')->orWhereNotNull('marked_ok_at')))
            ->columns([
                TextColumn::make('msgId')->label('Message ID')->searchable()->sortable(),

                TextColumn::make('callId')
                    ->label('Call ID')
                    ->searchable()
                    ->sortable()
                    ->url(fn (BoardCheckItem $record): string => '/utilities/call-lookup/'.$record->callId, shouldOpenInNewTab: true),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (BoardCheckItem $record): string => match (true) {
                        $record->marked_ok_at !== null => 'Approved',
                        $record->problem_verified_at !== null => 'Problem Verified',
                        $record->approved_at !== null => 'Awaiting Review',
                        $record->problem_found_at !== null => 'Problem Reported',
                        default => 'Needs Reviewed',
                    })
                    ->color(fn (BoardCheckItem $record): string => match (true) {
                        $record->marked_ok_at !== null => 'success',
                        $record->problem_verified_at !== null => 'danger',
                        default => 'warning',
                    })
                    ->tooltip(fn (BoardCheckItem $record): ?string => match (true) {
                        $record->marked_ok_at !== null => 'Approved by '.$record->marked_ok_by.' at '.$record->marked_ok_at,
                        $record->problem_verified_at !== null => 'Verified by '.$record->problem_verified_by.' at '.$record->problem_verified_at,
                        default => null,
                    }),

                TextColumn::make('category')
                    ->label('Category')
                    ->formatStateUsing(fn ($state): string => $categories[$state] ?? '')
                    ->sortable(),

                TextColumn::make('comments')
                    ->label('Comments')
                    ->wrap()
                    ->limit(80)
                    ->tooltip(fn (BoardCheckItem $record): string => $record->comments ?? 'No Comments')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Last Update')
                    ->dateTime()
                    ->color('gray')
                    ->sortable(),
            ])
            ->recordActions([
                $this->reviewAction(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No reviewed items yet.');
    }

    public function render(): View
    {
        BoardCheckActivity::create([
            'activity_type' => 'Viewed Board Report',
            'user_id' => Auth::user()->id,
        ]);

        return view('livewire.utilities.board-report');
    }
}
