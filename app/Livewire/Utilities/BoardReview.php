<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use Filament\Actions\Action;
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
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public $currentUrl;

    public $listeners = ['boardCheckSupervisorItemUpdated' => 'render'];

    public function mount(): void
    {
        $this->currentUrl = url()->current();
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
                Action::make('review')
                    ->label('Review Message')
                    ->icon('heroicon-m-magnifying-glass-circle')
                    ->link()
                    ->action(fn (BoardCheckItem $record) => $this->dispatch('openModal', component: 'utilities.board-supervisor-review-message', arguments: [
                        'msgId' => $record->msgId,
                        'isCallID' => $record->callId,
                    ])),
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
