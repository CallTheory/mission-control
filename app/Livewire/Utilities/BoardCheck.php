<?php

namespace App\Livewire\Utilities;

use App\Models\BoardCheckItem;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\Stats\BoardCheck\Fill as Recent;
use App\Models\System\Settings;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BoardCheck extends Component
{
    use WithPagination;

    protected $listeners = ['boardCheckItemUpdated' => 'getRecent'];

    public $currentUrl;

    public function clearRecords(): void
    {
        try {
            BoardCheckItem::truncate();
            BoardCheckActivity::create([
                'activity_type' => 'Cleared all records',
                'user_id' => Auth::user()->id,
            ]);
        } catch (Exception $e) {
        }

        $this->mount();
    }

    public function getRecent(): void
    {
        $settings = Settings::first();
        $configMsgId = config('utilities.board-check.starting_callid');
        $settingsMsgId = $settings->board_check_starting_msgId;
        $lastMsgId = BoardCheckItem::orderBy('msgId', 'desc')->limit(1)->first();

        if (! is_null($settingsMsgId) || ! is_null($lastMsgId->msgId ?? null) || ! is_null($configMsgId)) {
            try {
                $fill = new Recent([
                    'msgId' => $settingsMsgId ?? $lastMsgId->msgId ?? $configMsgId,
                ]);

                $fill->insertBoardCheckItems();
            } catch (Exception $e) {
                if (App::environment('local')) {
                    throw new Exception($e->getMessage());
                }
                Log::alert($e->getMessage());
            }
        }

        $this->mount();
    }

    public function mount(): void
    {
        $this->currentUrl = url()->current();
    }

    public function render(): View
    {
        return view('livewire.utilities.board-check', [
            'boardChecks' => BoardCheckItem::whereNull('approved_at')->whereNull('problem_found_at')->orderBy('msgId', 'asc')->paginate(25),
        ]);
    }
}
