<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Jobs\ProcessMessageExport;
use App\Models\MessageExport as MessageExportModel;
use App\Models\Stats\Clients\Overview;
use App\Models\Stats\Messages\AccountFieldDiscovery;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MessageExport extends Component
{
    use WithPagination;

    public int $editingRecord = 0;

    public bool $showCreateModal = false;

    public bool $showRunNowModal = false;

    public int $runNowExportId = 0;

    public array $state = [];

    public array $runNowState = [];

    public array $availableFields = [];

    public bool $loadingFields = false;

    public $listeners = ['saved' => '$refresh'];

    protected array $rules = [
        'state.name' => 'required|string|max:100',
        'state.client_number' => 'required|string|max:50',
        'state.selected_fields' => 'required|array|min:1',
        'state.filter_field' => 'nullable|string',
        'state.filter_value' => 'nullable|string',
        'state.include_call_info' => 'boolean',
        'state.recipients' => 'required|string',
        'state.subject' => 'required|string|max:255',
        'state.schedule_type' => 'required|in:manual,immediate,hourly,daily,weekly,monthly',
        'state.schedule_time' => 'nullable|string',
        'state.schedule_day_of_week' => 'nullable|integer|min:0|max:6',
        'state.schedule_day_of_month' => 'nullable|integer|min:1|max:31',
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
            'selected_fields' => [],
            'filter_field' => '',
            'filter_value' => '',
            'include_call_info' => true,
            'recipients' => '',
            'subject' => 'Message Export',
            'schedule_type' => 'manual',
            'schedule_time' => '08:00',
            'schedule_day_of_week' => 0,
            'schedule_day_of_month' => 1,
            'timezone' => 'America/New_York',
        ];
        $this->availableFields = [];
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

    public function updatedStateClientNumber(): void
    {
        $this->discoverFields();
    }

    public function discoverFields(): void
    {
        $this->availableFields = [];
        $this->state['selected_fields'] = [];

        if (empty($this->state['client_number'])) {
            return;
        }

        $this->loadingFields = true;

        try {
            $discovery = new AccountFieldDiscovery($this->state['client_number']);
            $this->availableFields = $discovery->getAvailableFields();
        } catch (Exception $e) {
            $this->availableFields = [];
        }

        $this->loadingFields = false;
    }

    public function selectAllFields(): void
    {
        $this->state['selected_fields'] = $this->availableFields;
    }

    public function deselectAllFields(): void
    {
        $this->state['selected_fields'] = [];
    }

    public function create(): void
    {
        $this->validate();

        $team = request()->user()->currentTeam;

        $isManual = $this->state['schedule_type'] === 'manual';
        $isImmediate = $this->state['schedule_type'] === 'immediate';
        $needsScheduleDetails = ! $isManual && ! $isImmediate;

        // Find client name for display
        $clientName = $this->findClientName($this->state['client_number']);

        $recipients = array_filter(array_map('trim', explode("\n", $this->state['recipients'])));

        MessageExportModel::create([
            'team_id' => $team->id,
            'name' => $this->state['name'],
            'client_number' => $this->state['client_number'],
            'client_name' => $clientName,
            'selected_fields' => $this->state['selected_fields'],
            'filter_field' => $this->state['filter_field'] ?: null,
            'filter_value' => $this->state['filter_value'] ?: null,
            'include_call_info' => $this->state['include_call_info'],
            'recipients' => $recipients,
            'subject' => $this->state['subject'],
            'schedule_type' => $this->state['schedule_type'],
            'schedule_time' => $needsScheduleDetails ? ($this->state['schedule_time'] ?: null) : null,
            'schedule_day_of_week' => $needsScheduleDetails ? $this->state['schedule_day_of_week'] : null,
            'schedule_day_of_month' => $needsScheduleDetails ? $this->state['schedule_day_of_month'] : null,
            'timezone' => $this->state['timezone'],
            'enabled' => true,
            'next_run_at' => null,
        ]);

        $this->closeCreateModal();
        $this->dispatch('saved');
    }

    public function edit(MessageExportModel $export): void
    {
        $this->state = [
            'name' => $export->name,
            'client_number' => $export->client_number,
            'selected_fields' => $export->selected_fields,
            'filter_field' => $export->filter_field ?? '',
            'filter_value' => $export->filter_value ?? '',
            'include_call_info' => $export->include_call_info,
            'recipients' => implode("\n", $export->recipients ?? []),
            'subject' => $export->subject,
            'schedule_type' => $export->schedule_type,
            'schedule_time' => $export->schedule_time ?? '08:00',
            'schedule_day_of_week' => $export->schedule_day_of_week ?? 0,
            'schedule_day_of_month' => $export->schedule_day_of_month ?? 1,
            'timezone' => $export->timezone,
        ];

        // Load available fields for the account
        try {
            $discovery = new AccountFieldDiscovery($export->client_number);
            $this->availableFields = $discovery->getAvailableFields();
        } catch (Exception $e) {
            $this->availableFields = [];
        }

        $this->editingRecord = $export->id;
    }

    public function closeEditModal(): void
    {
        $this->state = [];
        $this->editingRecord = 0;
        $this->availableFields = [];
    }

    public function update(MessageExportModel $export): void
    {
        $this->validate();

        $isManual = $this->state['schedule_type'] === 'manual';
        $isImmediate = $this->state['schedule_type'] === 'immediate';
        $needsScheduleDetails = ! $isManual && ! $isImmediate;

        $clientName = $this->findClientName($this->state['client_number']);

        $recipients = array_filter(array_map('trim', explode("\n", $this->state['recipients'])));

        $export->update([
            'name' => $this->state['name'],
            'client_number' => $this->state['client_number'],
            'client_name' => $clientName,
            'selected_fields' => $this->state['selected_fields'],
            'filter_field' => $this->state['filter_field'] ?: null,
            'filter_value' => $this->state['filter_value'] ?: null,
            'include_call_info' => $this->state['include_call_info'],
            'recipients' => $recipients,
            'subject' => $this->state['subject'],
            'schedule_type' => $this->state['schedule_type'],
            'schedule_time' => $needsScheduleDetails ? ($this->state['schedule_time'] ?: null) : null,
            'schedule_day_of_week' => $needsScheduleDetails ? $this->state['schedule_day_of_week'] : null,
            'schedule_day_of_month' => $needsScheduleDetails ? $this->state['schedule_day_of_month'] : null,
            'timezone' => $this->state['timezone'],
        ]);

        if (! $isManual) {
            $export->next_run_at = $export->calculateNextRunAt();
            $export->save();
        }

        $this->closeEditModal();
        $this->dispatch('saved');
    }

    public function delete(MessageExportModel $export): void
    {
        $export->delete();
        $this->dispatch('saved');
    }

    public function toggleEnabled(MessageExportModel $export): void
    {
        $export->enabled = ! $export->enabled;

        if ($export->enabled && ! $export->next_run_at && ! $export->isManual()) {
            $export->next_run_at = $export->calculateNextRunAt();
        }

        $export->save();
        $this->dispatch('saved');
    }

    public function openRunNowModal(int $exportId): void
    {
        $export = MessageExportModel::find($exportId);
        if (! $export) {
            return;
        }

        $this->runNowExportId = $exportId;

        [$defaultStart, $defaultEnd] = $export->getDateRange();

        $this->runNowState = [
            'start_date' => $defaultStart->format('Y-m-d\TH:i'),
            'end_date' => $defaultEnd->format('Y-m-d\TH:i'),
        ];

        $this->showRunNowModal = true;
    }

    public function closeRunNowModal(): void
    {
        $this->showRunNowModal = false;
        $this->runNowExportId = 0;
        $this->runNowState = [];
    }

    public function runNow(): void
    {
        $this->validate([
            'runNowState.start_date' => 'required|date',
            'runNowState.end_date' => 'required|date|after:runNowState.start_date',
        ]);

        $export = MessageExportModel::find($this->runNowExportId);
        if (! $export) {
            return;
        }

        $startDate = Carbon::parse($this->runNowState['start_date'], $export->timezone);
        $endDate = Carbon::parse($this->runNowState['end_date'], $export->timezone);

        ProcessMessageExport::dispatch($export, $startDate, $endDate, request()->user()->id);

        $this->closeRunNowModal();
        $this->dispatch('saved');

        session()->flash('message', 'Message export job has been queued. Check the Export History tab for results.');
    }

    public function getTimezones(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL);
    }

    public function getScheduleTypes(): array
    {
        return [
            'manual' => 'Manual (On-Demand Only)',
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

    private function findClientName(string $clientNumber): ?string
    {
        $clients = $this->getClients();
        foreach ($clients as $client) {
            if ((string) $client->ClientNumber === $clientNumber) {
                return $client->ClientName ?? null;
            }
        }

        return null;
    }

    public function render(): View
    {
        $team = request()->user()->currentTeam;

        $exports = MessageExportModel::where('team_id', $team->id)
            ->orderBy('created_at', 'desc')
            ->paginate(25);

        return view('livewire.utilities.message-export', [
            'exports' => $exports,
            'timezones' => $this->getTimezones(),
            'scheduleTypes' => $this->getScheduleTypes(),
            'daysOfWeek' => $this->getDaysOfWeek(),
            'clients' => $this->getClients(),
        ]);
    }
}
