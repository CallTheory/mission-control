<?php

namespace App\Livewire\Utilities;

use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BoardActivity extends Component
{
    use WithPagination;

    public string|int $msgId;

    public string|int $user_id;

    public $currentUrl;

    public function mount(?int $msgId = null, ?int $user_id = null): void
    {

        $this->currentUrl = url()->current();

        if ($msgId) {
            $this->msgId = $msgId;
        }

        if ($user_id) {
            $this->user_id = $user_id;
        }
    }

    public function render(): View
    {
        if (isset($this->msgId) && strlen($this->msgId) > 0) {
            $activity = BoardCheckActivity::where('msgId', (int) $this->msgId)->orderBy('created_at', 'desc')->paginate(25);
        } elseif (isset($this->user_id) && strlen($this->user_id) > 0) {
            $activity = BoardCheckActivity::where('user_id', (int) $this->user_id)->orderBy('created_at', 'desc')->paginate(25);
        } else {
            $activity = BoardCheckActivity::orderBy('created_at', 'desc')->paginate(25);
        }

        return view('livewire.utilities.board-activity', [
            'boardCheckActivity' => $activity,
        ]);
    }
}
