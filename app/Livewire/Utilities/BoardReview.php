<?php

namespace App\Livewire\Utilities;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesBoardComponent;
use App\Livewire\Concerns\ReviewsBoardCheckItems;
use App\Models\BoardCheckItem;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

class BoardReview extends Component implements HasActions, HasSchemas, HasTable
{
    use AuthorizesBoardComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::BoardReview;
    }

    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;
    use ReviewsBoardCheckItems;

    public $currentUrl;

    public $listeners = ['boardCheckSupervisorItemUpdated' => 'render'];

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
        return $table
            // Items a dispatcher has acted on but a supervisor has not yet resolved.
            ->query(fn (): Builder => BoardCheckItem::query()
                ->whereNull(['marked_ok_at', 'problem_verified_at'])
                ->where(fn (Builder $q) => $q->whereNotNull('approved_at')->orWhereNotNull('problem_found_at')))
            ->columns([
                TextColumn::make('msgId')->label('Message ID')->searchable()->sortable(),
                TextColumn::make('callId')->label('Call ID')->searchable()->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (BoardCheckItem $record): string => $record->problem_found_at ? 'Problem Found' : 'Approved')
                    ->color(fn (BoardCheckItem $record): string => $record->problem_found_at ? 'danger' : 'success'),
            ])
            ->recordActions([
                $this->reviewAction(),
            ])
            ->defaultSort('msgId')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Nothing to review')
            ->emptyStateDescription('There are no messages awaiting supervisor review.');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-review');
    }
}
