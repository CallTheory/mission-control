<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\BoardCheckItem;
use App\Models\Stats\Agents\Listing;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\Stats\Helpers;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * The board check review dialog, as a Filament action on the listing rather than a
 * separate wire-elements ModalComponent.
 *
 * Both stages -- dispatcher and supervisor -- are the same dialog: read the call, then
 * either accept the message or escalate it with a category and comments. What differs
 * is which timestamps the two outcomes write and what the activity log calls them, so
 * that is all a using component supplies.
 *
 * Accepting is the common path and is therefore the modal's submit button; escalating
 * is the exception and sits beside it, styled as destructive, because it moves the item
 * to someone else's queue.
 */
trait ReviewsBoardCheckItems
{
    /**
     * Label and activity-log wording for the accept outcome.
     *
     * @return array{label: string, activity: string}
     */
    abstract protected function acceptOutcome(): array;

    /**
     * Label and activity-log wording for the escalate outcome.
     *
     * @return array{label: string, activity: string}
     */
    abstract protected function escalateOutcome(): array;

    /**
     * Timestamp columns set when the message is accepted.
     *
     * @return array<int, string>
     */
    abstract protected function acceptColumns(): array;

    /**
     * Timestamp columns set when the message is escalated.
     *
     * @return array<int, string>
     */
    abstract protected function escalateColumns(): array;

    /**
     * Whether the escalate path also records a category, comments and a responsible
     * agent. The supervisor stage is verifying a problem someone else already
     * described, so it does not re-collect them.
     */
    protected function escalateCollectsDetail(): bool
    {
        return true;
    }

    public function reviewAction(): Action
    {
        $accept = $this->acceptOutcome();
        $escalate = $this->escalateOutcome();

        return Action::make('review')
            ->label('Review Message')
            ->icon('heroicon-m-magnifying-glass-circle')
            ->link()
            ->modalHeading(fn (BoardCheckItem $record): string => 'Review message '.$record->msgId)
            ->modalDescription('Verify the message is correct and accurate according to company standards.')
            ->modalWidth('7xl')
            // The call detail is an existing Livewire component; rendering it as modal
            // content keeps that screen the single implementation of call lookup.
            ->modalContent(fn (BoardCheckItem $record) => view('livewire.utilities.partials.board-review-call', [
                'isCallID' => $record->callId,
            ]))
            ->schema($this->escalateCollectsDetail() ? [
                Select::make('category')
                    ->label('Category')
                    ->options(Helpers::boardCheckCategories())
                    ->placeholder('Select a category'),

                Textarea::make('comments')
                    ->label('Comments')
                    ->rows(3),

                Select::make('agtId')
                    ->label('Responsible Agent')
                    ->options(fn (): array => $this->responsibleAgentOptions())
                    ->searchable()
                    ->placeholder('Not attributed'),
            ] : [])
            ->fillForm(fn (BoardCheckItem $record): array => $this->reviewFormState($record))
            ->modalSubmitActionLabel($accept['label'])
            ->action(fn (BoardCheckItem $record) => $this->recordBoardOutcome(
                $record,
                $this->acceptColumns(),
                $accept['activity'],
            ))
            ->extraModalFooterActions([
                Action::make('escalate')
                    ->label($escalate['label'])
                    ->color('danger')
                    ->action(function (BoardCheckItem $record, array $data): void {
                        $this->recordBoardOutcome(
                            $record,
                            $this->escalateColumns(),
                            $escalate['activity'],
                            $this->escalateCollectsDetail() ? $data : [],
                        );
                    })
                    ->cancelParentActions(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function reviewFormState(BoardCheckItem $item): array
    {
        return [
            'category' => $item->category,
            'comments' => $item->comments,
            'agtId' => $item->agtId,
        ];
    }

    /**
     * @return array<int|string, string>
     */
    protected function responsibleAgentOptions(): array
    {
        try {
            return collect((new Listing)->results)
                ->pluck('Name', 'agtId')
                ->all();
        } catch (\Throwable) {
            // The agent list comes from Amtelco; an unreachable database leaves the
            // select empty rather than blocking the review.
            return [];
        }
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<string, mixed>  $detail
     */
    protected function recordBoardOutcome(BoardCheckItem $item, array $columns, string $activity, array $detail = []): void
    {
        $now = Carbon::now();
        $email = Auth::user()->email;

        foreach ($columns as $column) {
            $item->{$column.'_at'} = $now;
            $item->{$column.'_by'} = $email;
        }

        if ($detail !== []) {
            $item->category = $detail['category'] ?? null;
            $item->comments = $detail['comments'] ?? null;
            // An unattributed message stores NULL rather than an empty string.
            $item->agtId = blank($detail['agtId'] ?? null) ? null : $detail['agtId'];
        }

        $item->save();

        BoardCheckActivity::create([
            'activity_type' => $activity,
            'user_id' => Auth::user()->id,
            'msgId' => $item->msgId,
        ]);

        Notification::make()->title($activity)->success()->send();

        $this->dispatch('boardCheckItemUpdated');
    }
}
