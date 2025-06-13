<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class BoardConfirmProblem extends ModalComponent
{
    public int $msgId;

    public array $state;

    public $currentUrl;

    public static function modalMaxWidthClass(): string
    {
        return 'max-w-7xl';
    }

    public function mount(int $msgId): void
    {
        $this->currentUrl = url()->current();
        $this->msgId = $msgId;
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();
        $this->state['comments'] = $item->comments ?? null;
        $this->state['category'] = $item->category ?? null;
        BoardCheckActivity::create([
            'activity_type' => 'Opened',
            'user_id' => Auth::user()->id,
            'msgId' => $this->msgId,
        ]);
    }

    public function confirmProblem(): void
    {
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();
        if ($item) {
            $item->problem_verified_at = Carbon::now();
            $item->problem_verified_by = Auth::user()->email;
            $item->save();
        }

        BoardCheckActivity::create([
            'activity_type' => 'Confirmed Problem',
            'user_id' => Auth::user()->id,
            'msgId' => $this->msgId,
        ]);

        $this->dispatch('boardCheckSupervisorItemUpdated');
        $this->dispatch('closeModal');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-confirm-problem');
    }
}
