<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\CsvExportLog;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CsvExportHistory extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public int $filterUser = 0;

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUser(): void
    {
        $this->resetPage();
    }

    public function reexport(CsvExportLog $log): void
    {
        $team = request()->user()->currentTeam;

        if ((int) $log->team_id !== (int) $team->id) {
            session()->flash('error', 'Export log not found.');

            return;
        }

        $this->redirect(route('utilities.csv-export', ['reexport_log_id' => $log->id]));
    }

    public function render(): View
    {
        $team = request()->user()->currentTeam;

        $query = CsvExportLog::forTeam($team->id)
            ->with('user')
            ->orderBy('created_at', 'desc');

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterUser > 0) {
            $query->where('user_id', $this->filterUser);
        }

        $logs = $query->paginate(25);

        $users = User::whereIn('id', CsvExportLog::forTeam($team->id)->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.utilities.csv-export-history', [
            'logs' => $logs,
            'users' => $users,
        ]);
    }
}
