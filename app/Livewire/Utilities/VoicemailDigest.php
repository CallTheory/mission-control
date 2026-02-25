<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Jobs\SendVoicemailDigest as SendVoicemailDigestJob;
use App\Models\Stats\Clients\Overview;
use App\Models\VoicemailDigest as VoicemailDigestModel;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class VoicemailDigest extends Component
{
    use WithPagination;

    public int $editingRecord = 0;

    public bool $showCreateModal = false;

    public bool $showSendNowModal = false;

    public int $sendNowScheduleId = 0;

    public array $state = [];

    public array $sendNowState = [];

    public $listeners = ['saved' => '$refresh'];

    protected array $rules = [
        'state.name' => 'required|string|max:100',
        'state.client_number' => 'nullable|string|max:50',
        'state.billing_code' => 'nullable|string|max:50',
        'state.recipients' => 'required|string',
        'state.subject' => 'required|string|max:255',
        'state.schedule_type' => 'required|in:immediate,hourly,daily,weekly,monthly',
        'state.schedule_time' => 'nullable|string',
        'state.schedule_day_of_week' => 'nullable|integer|min:0|max:6',
        'state.schedule_day_of_month' => 'nullable|integer|min:1|max:31',
        'state.include_transcription' => 'boolean',
        'state.include_call_metadata' => 'boolean',
        'state.timezone' => 'required|string',
    ];

    public function mount(): void
    {
        $this->resetState();
    }

    public function resetState(): void
    {
        $this->state = [
            'name' => '',
            'client_number' => '',
            'billing_code' => '',
            'recipients' => '',
            'subject' => 'Voicemail Digest',
            'schedule_type' => 'daily',
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 0,
            'schedule_day_of_month' => 1,
            'include_transcription' => true,
            'include_call_metadata' => true,
            'timezone' => 'America/New_York',
        ];
    }

    public function openCreateModal(): void
    {
        $this->resetState();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetState();
    }

    public function create(): void
    {
        $this->validate();

        $team = request()->user()->currentTeam;

        $isImmediate = $this->state['schedule_type'] === 'immediate';

        VoicemailDigestModel::create([
            'team_id' => $team->id,
            'name' => $this->state['name'],
            'client_number' => $this->state['client_number'] ?: null,
            'billing_code' => $this->state['billing_code'] ?: null,
            'recipients' => array_filter(array_map('trim', explode("\n", $this->state['recipients']))),
            'subject' => $this->state['subject'],
            'schedule_type' => $this->state['schedule_type'],
            'schedule_time' => $isImmediate ? null : ($this->state['schedule_time'] ?: null),
            'schedule_day_of_week' => $isImmediate ? null : $this->state['schedule_day_of_week'],
            'schedule_day_of_month' => $isImmediate ? null : $this->state['schedule_day_of_month'],
            'include_transcription' => $this->state['include_transcription'],
            'include_call_metadata' => $this->state['include_call_metadata'],
            'timezone' => $this->state['timezone'],
            'enabled' => true,
            'next_run_at' => null,
        ]);

        $this->closeCreateModal();
        $this->dispatch('saved');
    }

    public function edit(VoicemailDigestModel $schedule): void
    {
        $this->state = [
            'name' => $schedule->name,
            'client_number' => $schedule->client_number ?? '',
            'billing_code' => $schedule->billing_code ?? '',
            'recipients' => implode("\n", $schedule->recipients ?? []),
            'subject' => $schedule->subject,
            'schedule_type' => $schedule->schedule_type,
            'schedule_time' => $schedule->schedule_time ?? '08:00',
            'schedule_day_of_week' => $schedule->schedule_day_of_week ?? 0,
            'schedule_day_of_month' => $schedule->schedule_day_of_month ?? 1,
            'include_transcription' => $schedule->include_transcription,
            'include_call_metadata' => $schedule->include_call_metadata,
            'timezone' => $schedule->timezone,
        ];
        $this->editingRecord = $schedule->id;
    }

    public function closeEditModal(): void
    {
        $this->state = [];
        $this->editingRecord = 0;
    }

    public function update(VoicemailDigestModel $schedule): void
    {
        $this->validate();

        $isImmediate = $this->state['schedule_type'] === 'immediate';

        $schedule->update([
            'name' => $this->state['name'],
            'client_number' => $this->state['client_number'] ?: null,
            'billing_code' => $this->state['billing_code'] ?: null,
            'recipients' => array_filter(array_map('trim', explode("\n", $this->state['recipients']))),
            'subject' => $this->state['subject'],
            'schedule_type' => $this->state['schedule_type'],
            'schedule_time' => $isImmediate ? null : ($this->state['schedule_time'] ?: null),
            'schedule_day_of_week' => $isImmediate ? null : $this->state['schedule_day_of_week'],
            'schedule_day_of_month' => $isImmediate ? null : $this->state['schedule_day_of_month'],
            'include_transcription' => $this->state['include_transcription'],
            'include_call_metadata' => $this->state['include_call_metadata'],
            'timezone' => $this->state['timezone'],
        ]);

        // Recalculate next run time
        $schedule->next_run_at = $schedule->calculateNextRunAt();
        $schedule->save();

        $this->closeEditModal();
        $this->dispatch('saved');
    }

    public function delete(VoicemailDigestModel $schedule): void
    {
        $schedule->delete();
        $this->dispatch('saved');
    }

    public function toggleEnabled(VoicemailDigestModel $schedule): void
    {
        $schedule->enabled = ! $schedule->enabled;

        if ($schedule->enabled && ! $schedule->next_run_at) {
            $schedule->next_run_at = $schedule->calculateNextRunAt();
        }

        $schedule->save();
        $this->dispatch('saved');
    }

    public function openSendNowModal(int $scheduleId): void
    {
        $schedule = VoicemailDigestModel::find($scheduleId);
        if (! $schedule) {
            return;
        }

        $this->sendNowScheduleId = $scheduleId;

        // Pre-fill dates based on schedule type
        [$defaultStart, $defaultEnd] = $schedule->getDateRange();

        $this->sendNowState = [
            'start_date' => $defaultStart->format('Y-m-d\TH:i'),
            'end_date' => $defaultEnd->format('Y-m-d\TH:i'),
        ];

        $this->showSendNowModal = true;
    }

    public function closeSendNowModal(): void
    {
        $this->showSendNowModal = false;
        $this->sendNowScheduleId = 0;
        $this->sendNowState = [];
    }

    public function sendNow(): void
    {
        $this->validate([
            'sendNowState.start_date' => 'required|date',
            'sendNowState.end_date' => 'required|date|after:sendNowState.start_date',
        ]);

        $schedule = VoicemailDigestModel::find($this->sendNowScheduleId);
        if (! $schedule) {
            return;
        }

        $startDate = Carbon::parse($this->sendNowState['start_date'], $schedule->timezone);
        $endDate = Carbon::parse($this->sendNowState['end_date'], $schedule->timezone);

        SendVoicemailDigestJob::dispatch($schedule, $startDate, $endDate);

        $this->closeSendNowModal();
        $this->dispatch('saved');

        session()->flash('message', 'Voicemail digest job has been queued.');
    }

    public function getTimezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    public function getScheduleTypes(): array
    {
        return [
            'immediate' => 'Immediate',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ];
    }

    public function getDaysOfWeek(): array
    {
        return [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];
    }

    public function getClients(): array
    {
        $team = request()->user()->currentTeam;

        try {
            $clients = new Overview([
                'order_by' => 'ClientNumber',
                'order_direction' => 'asc',
                'client_number' => '',
                'client_name' => '',
                'billing_code' => '',
                'allowed_accounts' => $team->allowed_accounts,
                'allowed_billing' => $team->allowed_billing,
                'account_setting' => '',
                'account_setting_value' => '',
                'client_source' => '',
            ]);

            return $clients->results ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function render(): View
    {
        $team = request()->user()->currentTeam;

        $schedules = VoicemailDigestModel::where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('livewire.utilities.voicemail-digest', [
            'schedules' => $schedules,
            'timezones' => $this->getTimezones(),
            'scheduleTypes' => $this->getScheduleTypes(),
            'daysOfWeek' => $this->getDaysOfWeek(),
            'clients' => $this->getClients(),
        ]);
    }
}
