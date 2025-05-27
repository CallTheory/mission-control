<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use App\Models\Stats\Calls\Call;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;

class BoardDispatcherReviewMessage extends ModalComponent
{
    public int $msgId;

    public int|null $isCallID = null;

    public array $state = [];

    /**
     * @throws Exception
     */
    public function lookupCall(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'isCallID' => 'required|numeric|between:1,10000000000',
        ], [
            'isCallID' => $this->isCallID ?? null,
        ]);

        try {
            $call = new Call(['ISCallId' => $this->isCallID]);
        } catch (Exception $e) {
            $this->addError('isCallID', $e->getMessage());

            return;
        }

        $this->state['details'] = $call->details();
        $this->state['messages'] = $call->messages();
        $this->state['history'] = $call->history();
        $this->state['statistics'] = $call->statistics();
        $this->state['recordings'] = $call->recordings();
        $this->state['agents'] = $call->agents();
        $this->state['clients'] = $call->clients();
        $this->state['tracker'] = $call->tracker();

        $this->dispatch('search');
    }

    /**
     * @throws Exception
     */
    public function mount(int $msgId, int $isCallID = null): void
    {
        $this->isCallID = $isCallID;

        if ($this->isCallID) {
            $this->lookupCall();
        }

        $this->msgId = $msgId;
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();

        $this->state['comments'] = $item->comments ?? null;
        $this->state['category'] = $item->category ?? null;
        $this->state['agtId'] = $item->agtId ?? null;
        BoardCheckActivity::create([
            'activity_type' => "Opened",
            'user_id' => Auth::user()->id,
            'msgId' => $this->msgId,
        ]);
    }

    public function flagMessage(): void
    {
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();

        if ($item) {
            $item->problem_found_at = Carbon::now();
            $item->problem_found_by = Auth::user()->email;
            $item->comments = $this->state['comments'];
            $item->category = $this->state['category'];
            if ($this->state['agtId'] === '') {
                $item->agtId = null;
            } else {
                $item->agtId = $this->state['agtId'];
            }

            $item->save();

            BoardCheckActivity::create([
                'activity_type' => "Dispatcher Flagged",
                'user_id' => Auth::user()->id,
                'msgId' => $this->msgId,
            ]);
        }

        $this->dispatch('boardCheckItemUpdated');
        $this->dispatch('closeModal');
    }

    public function confirmMessage(): void
    {
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();

        if ($item) {
            $item->marked_ok_at = Carbon::now();
            $item->marked_ok_by = Auth::user()->email;
            $item->approved_at = Carbon::now();
            $item->approved_by = Auth::user()->email;
            $item->save();

            BoardCheckActivity::create([
                'activity_type' => "Dispatcher Approved",
                'user_id' => Auth::user()->id,
                'msgId' => $this->msgId,
            ]);
        }

        $this->dispatch('boardCheckItemUpdated');
        $this->dispatch('closeModal');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-dispatcher-review-message');
    }

    public static function modalMaxWidthClass(): string
    {
        return 'max-w-7xl';
    }
}
