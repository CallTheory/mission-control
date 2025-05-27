<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;

class BoardApproveMessage extends ModalComponent
{
    public int $msgId;

    public static function modalMaxWidthClass(): string
    {
        return 'max-w-7xl';
    }

    public function mount(int $msgId): void
    {
        $this->msgId = $msgId;
        BoardCheckActivity::create([
            'activity_type' => "Opened",
            'user_id' => Auth::user()->id,
            'msgId' => $this->msgId,
        ]);
    }

    public function confirmMessage(): void
    {
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();
        if ($item) {
            $item->approved_at = Carbon::now();
            $item->approved_by = Auth::user()->email;
            $item->save();
        }

        BoardCheckActivity::create([
            'activity_type' => "Confirmed Message",
            'user_id' => Auth::user()->id,
            'msgId' => $this->msgId,
        ]);

        $this->dispatch('boardCheckItemUpdated');
        $this->dispatch('closeModal');
    }
    public function render(): View
    {
        return view('livewire.utilities.board-approve-message');
    }
}
