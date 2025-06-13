<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BoardReview extends Component
{
    use WithPagination;

    public $currentUrl;

    public $listeners = ['boardCheckSupervisorItemUpdated' => 'render'];

    public function mount(): void
    {
        $this->currentUrl = url()->current();
    }

    public function render(): View
    {
        return view('livewire.utilities.board-review', [
            'boardChecks' => BoardCheckItem::whereNull(['marked_ok_at', 'problem_verified_at'])
                ->where(function ($query) {
                    $query->whereNotNull('approved_at')->orWhereNotNull('problem_found_at');
                })->orderBy('msgId', 'asc')->paginate(25),
        ]);
    }
}
