<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\FiltersCallLog;
use App\Models\CsvExportLog;
use App\Models\Stats\Helpers;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport extends Component implements HasActions, HasSchemas
{
    use FiltersCallLog;
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Filter state. Shares its shape with Utilities\CallLog's table filters, because
     * both come from {@see FiltersCallLog}.
     *
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /** Result count from the last preview, so the operator knows what they will get. */
    public int $result_count = 0;

    public bool $queried = false;

    public string $error_message = '';

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->callLogFilterSchema())
            ->columns(3)
            ->statePath('data');
    }

    /**
     * Count the matching calls without downloading them. The old screen called this
     * "Apply Filter" and persisted every field to the session by hand; the form keeps
     * its own state, so this only has to run the query.
     */
    public function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview Count')
            ->action(function (): void {
                $this->error_message = '';

                try {
                    $this->result_count = count($this->callLogQuery($this->data ?? [])->results ?? []);
                    $this->queried = true;
                } catch (Exception $e) {
                    $this->result_count = 0;
                    $this->queried = false;
                    $this->error_message = $e->getMessage();

                    Notification::make()->title('Unable to query call data.')->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($this->result_count.' '.str('call')->plural($this->result_count).' match these filters.')
                    ->success()
                    ->send();
            });
    }

    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export CSV')
            ->color('primary')
            ->action(fn (): StreamedResponse => $this->exportCsv());
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterArray(): array
    {
        return $this->data ?? [];
    }

    public function exportCsv(): StreamedResponse
    {
        $log = CsvExportLog::create([
            'user_id' => request()->user()->id,
            'team_id' => request()->user()->currentTeam->id,
            'filters' => $this->getFilterArray(),
            'status' => 'completed',
        ]);

        try {
            $callLog = new CallLogStats(
                Carbon::parse(($this->data['start_date'] ?? null))->format('Y-m-d H:i:s'),
                Carbon::parse(($this->data['end_date'] ?? null))->format('Y-m-d H:i:s'),
                $this->timezone,
                ($this->data['client_number'] ?? null),
                ($this->data['ani'] ?? null),
                ($this->data['call_type'] ?? null),
                ($this->data['agent'] ?? null),
                ($this->data['min_duration'] ?? null),
                ($this->data['max_duration'] ?? null),
                ($this->data['keyword'] ?? null),
                ($this->data['keyword_search'] ?? null),
                'statCallStart.Stamp',
                'desc',
                (bool) ($this->data['has_messages'] ?? false),
                (bool) ($this->data['has_recordings'] ?? false),
                (bool) ($this->data['has_video'] ?? false),
                ! (bool) ($this->data['has_messages'] ?? false) && ! (bool) ($this->data['has_recordings'] ?? false) && ! (bool) ($this->data['has_video'] ?? false),
                request()->user()->currentTeam->allowed_accounts,
                request()->user()->currentTeam->allowed_billing,
            );

            $results = $callLog->results ?? [];
            $ck = Helpers::callTypes();
            $st = Helpers::stationTypes();

            $filename = 'call-log-export-'.now($this->timezone)->format('Y-m-d_His').'.csv';

            $log->markAsCompleted(count($results), $filename);
        } catch (Exception $e) {
            $log->markAsFailed($e->getMessage());

            throw $e;
        }

        return response()->streamDownload(function () use ($results, $ck, $st) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Call ID',
                'Client Number',
                'Client Name',
                'Billing Code',
                'Call Start',
                'Call End',
                'Channel',
                'Caller ANI',
                'Caller Name',
                'Caller DNIS',
                'Diversion',
                'Diversion Reason',
                'Call Type',
                'Completion Code',
                'Station Type',
                'Station Number',
                'Last Route',
                'Skill ID',
                'Skill Name',
                'Call Note',
                'Agent ID',
                'Agent Name',
                'Agent Initials',
                'Agent List',
                'Duration',
                'Duration (seconds)',
                'Has Messages',
                'Has Recordings',
                'Has Video',
            ]);

            foreach ($results as $row) {
                $callStart = $row->CallStart
                    ? Carbon::parse($row->CallStart, $this->timezone)->format('m/d/Y g:i:s A')
                    : '';
                $callEnd = $row->CallEnd
                    ? Carbon::parse($row->CallEnd, $this->timezone)->format('m/d/Y g:i:s A')
                    : '';

                $durationSeconds = (int) ($row->CallDuration ?? 0);

                fputcsv($handle, [
                    $row->CallId ?? '',
                    $row->ClientNumber ?? '',
                    $row->ClientName ?? '',
                    $row->BillingCode ?? '',
                    $callStart,
                    $callEnd,
                    $row->Channel ?? '',
                    $row->CallerANI ?? '',
                    $row->CallerName ?? '',
                    $row->CallerDNIS ?? '',
                    $row->Diversion ?? '',
                    $row->DiversionReason ?? '',
                    $ck[$row->Kind] ?? $row->Kind ?? '',
                    $row->CompCode ?? '',
                    $st[$row->stationType] ?? $row->stationType ?? '',
                    $row->stationNumber ?? '',
                    $row->LastRoute ?? '',
                    $row->SkillId ?? '',
                    $row->SkillName ?? '',
                    $row->CallNote ?? '',
                    $row->agtId ?? '',
                    $row->AgentName ?? '',
                    $row->AgentInitials ?? '',
                    $row->AgentList ?? '',
                    Helpers::formatDuration($durationSeconds),
                    $durationSeconds,
                    $row->hasMessages ? 'Yes' : 'No',
                    $row->hasRecordings ? 'Yes' : 'No',
                    $row->hasVideo ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function render(): View
    {
        return view('livewire.utilities.csv-export');
    }
}
