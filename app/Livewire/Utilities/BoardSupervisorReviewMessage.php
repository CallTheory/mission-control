<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\Stats\Calls\Call;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class BoardSupervisorReviewMessage extends ModalComponent
{
    public int $msgId;

    public ?int $isCallID = null;

    public array $state = [];

    public function mount(int $msgId, ?int $isCallID = null): void
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
            'activity_type' => 'Opened',
            'user_id' => Auth::user()->id,
            'msgId' => $this->msgId,
        ]);
    }

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

    public function confirmProblem(): void
    {
        if (is_null($this->state['agtId'])) {
            $this->mount($this->msgId, $this->isCallID);
            $this->addError('state.agtId', 'An agent must be selected');

            return;
        }

        $item = BoardCheckItem::where('msgId', $this->msgId)->first();
        if ($item) {
            $item->problem_verified_at = Carbon::now();
            $item->problem_verified_by = Auth::user()->email;
            $item->agtId = $this->state['agtId'];
            $item->save();
            BoardCheckActivity::create([
                'activity_type' => 'Supervisor Confirmed',
                'user_id' => Auth::user()->id,
                'msgId' => $this->msgId,
            ]);
        }

        $this->dispatch('boardCheckSupervisorItemUpdated');
        $this->dispatch('closeModal');
    }

    public function confirmMessage(): void
    {
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();
        if ($item) {
            $item->marked_ok_at = Carbon::now();
            $item->marked_ok_by = Auth::user()->email;
            $item->save();
            BoardCheckActivity::create([
                'activity_type' => 'Confirmed Message',
                'user_id' => Auth::user()->id,
                'msgId' => $this->msgId,
            ]);
        }

        $this->dispatch('boardCheckSupervisorItemUpdated');
        $this->dispatch('closeModal');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-supervisor-review-message');
    }

    public static function modalMaxWidthClass(): string
    {
        return 'max-w-7xl';
    }
}
