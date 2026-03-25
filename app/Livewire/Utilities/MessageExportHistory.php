<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\MessageExport;
use App\Models\MessageExportLog;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MessageExportHistory extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public int $filterExport = 0;

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterExport(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $team = request()->user()->currentTeam;

        $query = MessageExportLog::forTeam($team->id)
            ->with('messageExport', 'user')
            ->orderBy('created_at', 'desc');

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterExport > 0) {
            $query->where('message_export_id', $this->filterExport);
        }

        $logs = $query->paginate(25);

        $exports = MessageExport::where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.utilities.message-export-history', [
            'logs' => $logs,
            'exports' => $exports,
        ]);
    }
}
