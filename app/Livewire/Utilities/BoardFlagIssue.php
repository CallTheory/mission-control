<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class BoardFlagIssue extends ModalComponent
{
    public int $msgId;

    public array $state;

    public function mount(int $msgId): void
    {
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

    public function flagMessage(): void
    {
        $item = BoardCheckItem::where('msgId', $this->msgId)->first();

        if ($item) {
            $item->problem_found_at = Carbon::now();
            $item->problem_found_by = Auth::user()->email;
            $item->comments = $this->state['comments'];
            $item->category = $this->state['category'];
            $item->save();

            BoardCheckActivity::create([
                'activity_type' => 'Flagged Message',
                'user_id' => Auth::user()->id,
                'msgId' => $this->msgId,
            ]);
        }

        $this->dispatch('boardCheckItemUpdated');
        $this->dispatch('closeModal');
    }

    public function render(): View
    {
        return view('livewire.utilities.board-flag-issue');
    }

    public static function modalMaxWidthClass(): string
    {
        return 'max-w-7xl';
    }
}
