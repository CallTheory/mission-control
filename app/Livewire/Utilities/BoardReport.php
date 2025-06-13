<?php

namespace App\Livewire\Utilities;

use App\Jobs\ExportBoardCheckForPeoplePraise;
use App\Jobs\PeoplePraiseApi\ExportBoardCheckForPeoplePraiseApi;
use App\Models\BoardCheckItem;
use App\Models\DataSource;
use App\Models\Stats\BoardCheck\Activity as BoardCheckActivity;
use App\Models\System\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class BoardReport extends Component
{
    use WithPagination;

    public $currentUrl;

    public $listeners = ['boardCheckSupervisorItemUpdated' => 'render'];

    private DataSource $datasource;

    private Settings $settings;

    public function exportPeopleSoft(): void
    {
        $this->settings = Settings::first();
        if ($this->settings->board_check_people_praise_export_method === 'file') {
            ExportBoardCheckForPeoplePraise::dispatch();
            BoardCheckActivity::create([
                'activity_type' => 'Exported to file',
                'user_id' => Auth::user()->id,
            ]);
        } elseif ($this->settings->board_check_people_praise_export_method === 'api') {
            ExportBoardCheckForPeoplePraiseApi::dispatch();
            BoardCheckActivity::create([
                'activity_type' => 'Exported to People Praise API',
                'user_id' => Auth::user()->id,
            ]);
        } else {
            BoardCheckActivity::create([
                'activity_type' => "Export Failed: Expected \"file\" or \"api\" but got {$this->settings->board_check_people_praise_export_method}",
                'user_id' => Auth::user()->id,
            ]);
        }

        $this->dispatch('saved');
        $this->dispatch('boardCheckSupervisorItemUpdated');
    }

    public function markOK(BoardCheckItem $item): void
    {
        $item->marked_ok_at = Carbon::now();
        $item->marked_ok_by = Auth::user()->email;
        BoardCheckActivity::create([
            'activity_type' => 'Marked OK',
            'user_id' => Auth::user()->id,
            'msgId' => $item->msgId,
        ]);
        $item->save();
        $this->dispatch('saved');
        $this->dispatch('boardCheckSupervisorItemUpdated');
    }

    public function problemConfirmed(BoardCheckItem $item): void
    {
        $item->problem_verified_at = Carbon::now();
        $item->problem_verified_by = Auth::user()->email;
        BoardCheckActivity::create([
            'activity_type' => 'Confirmed Problem',
            'user_id' => Auth::user()->id,
            'msgId' => $item->msgId,
        ]);
        $item->save();
        $this->dispatch('saved');
        $this->dispatch('boardCheckSupervisorItemUpdated');
    }

    public function mount(): void
    {
        $this->settings = Settings::first();
        $this->datasource = DataSource::first();
        $this->currentUrl = url()->current();
    }

    public function render(): View
    {
        BoardCheckActivity::create([
            'activity_type' => 'Viewed Board Report',
            'user_id' => Auth::user()->id,
        ]);

        return view('livewire.utilities.board-report', [
            'boardChecks' => BoardCheckItem::whereNotNull('problem_verified_at')->orWhereNotNull('marked_ok_at')->paginate(25),
        ]);
    }
}
