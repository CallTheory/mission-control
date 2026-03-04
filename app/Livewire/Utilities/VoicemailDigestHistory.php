<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Jobs\SendVoicemailDigest;
use App\Models\VoicemailDigestLog;
use Carbon\Carbon;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class VoicemailDigestHistory extends Component
{
    use WithPagination;

    public string $filterStatus = '';

    public int $filterSchedule = 0;

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSchedule(): void
    {
        $this->resetPage();
    }

    public function resend(VoicemailDigestLog $log): void
    {
        $digest = $log->voicemailDigest;

        if (! $digest) {
            session()->flash('error', 'The parent schedule no longer exists.');

            return;
        }

        $startDate = Carbon::parse($log->start_date, $digest->timezone);
        $endDate = Carbon::parse($log->end_date, $digest->timezone);

        SendVoicemailDigest::dispatch($digest, $startDate, $endDate);

        session()->flash('message', 'Voicemail digest has been queued for resend.');
    }

    public function render(): View
    {
        $team = request()->user()->currentTeam;

        $query = VoicemailDigestLog::forTeam($team->id)
            ->with('voicemailDigest')
            ->orderBy('created_at', 'desc');

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterSchedule > 0) {
            $query->where('voicemail_digest_id', $this->filterSchedule);
        }

        $logs = $query->paginate(25);

        $schedules = \App\Models\VoicemailDigest::where('team_id', $team->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.utilities.voicemail-digest-history', [
            'logs' => $logs,
            'schedules' => $schedules,
        ]);
    }
}
