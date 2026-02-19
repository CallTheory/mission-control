<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\Stats\Agents\Listing;
use App\Models\Stats\Calls\CallLog as CallLogStats;
use App\Models\Stats\Helpers;
use App\Models\Stats\Messages\Keywords;
use App\Models\System\Settings;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport extends Component
{
    public array $ck = [];

    public array $st = [];

    public array $agents = [];

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $client_number = null;

    public ?string $ani = null;

    public ?string $call_type = null;

    public ?string $agent = null;

    public ?string $min_duration = null;

    public ?string $max_duration = null;

    public ?string $keyword = null;

    public ?string $keyword_search = null;

    public bool $has_any = true;

    public bool $has_messages = false;

    public bool $has_recordings = false;

    public bool $has_video = false;

    #[Locked]
    public string $sort_by = 'statCallStart.Stamp';

    public string $sort_direction = 'desc';

    public string $timezone = 'UTC';

    public array $keywords = [];

    public int $result_count = 0;

    public bool $queried = false;

    public string $error_message = '';

    public function mount(): void
    {
        try {
            $agents = new Listing;
            $this->agents = $agents->results;
        } catch (Exception $e) {
            $this->agents = [];
        }

        try {
            $keywords = new Keywords;
            $this->keywords = $keywords->results;
        } catch (Exception $e) {
            $this->keywords = [];
        }

        $ck = Helpers::callTypes();
        asort($ck);
        $this->ck = $ck;
        $this->st = Helpers::stationTypes();
        $settings = Settings::firstOrFail();

        if (! is_null($settings)) {
            $this->timezone = $settings->switch_data_timezone ?? 'UTC';
        }

        $this->start_date = Session::get('csv_export:filter:start_date', now($this->timezone)->subHours()->format('Y-m-d\TH:i'));
        $this->end_date = Session::get('csv_export:filter:end_date', now($this->timezone)->format('Y-m-d\TH:i'));
        $this->client_number = Session::get('csv_export:filter:client_number');
        $this->ani = Session::get('csv_export:filter:ani');
        $this->call_type = Session::get('csv_export:filter:call_type');
        $this->agent = Session::get('csv_export:filter:agent');
        $this->min_duration = Session::get('csv_export:filter:min_duration');
        $this->max_duration = Session::get('csv_export:filter:max_duration');
        $this->keyword = Session::get('csv_export:filter:keyword');
        $this->keyword_search = Session::get('csv_export:filter:keyword_search');
        $this->sort_by = Session::get('csv_export:filter:sort_by', 'statCallStart.Stamp');
        $this->sort_direction = Session::get('csv_export:filter:sort_direction', 'desc');

        $this->has_any = Session::get('csv_export:filter:has_any', true);
        $this->has_messages = Session::get('csv_export:filter:has_messages', false);
        $this->has_recordings = Session::get('csv_export:filter:has_recordings', false);
        $this->has_video = Session::get('csv_export:filter:has_video', false);
    }

    public function applyFilter(): void
    {
        Session::put('csv_export:filter:start_date', $this->start_date);
        Session::put('csv_export:filter:end_date', $this->end_date);
        Session::put('csv_export:filter:client_number', $this->client_number);
        Session::put('csv_export:filter:ani', $this->ani);
        Session::put('csv_export:filter:call_type', $this->call_type);
        Session::put('csv_export:filter:agent', $this->agent);
        Session::put('csv_export:filter:min_duration', $this->min_duration);
        Session::put('csv_export:filter:max_duration', $this->max_duration);
        Session::put('csv_export:filter:keyword', $this->keyword);
        Session::put('csv_export:filter:keyword_search', $this->keyword_search);
        Session::put('csv_export:filter:sort_by', $this->sort_by);
        Session::put('csv_export:filter:sort_direction', $this->sort_direction);

        Session::put('csv_export:filter:has_any', $this->has_any);
        Session::put('csv_export:filter:has_messages', $this->has_messages);
        Session::put('csv_export:filter:has_recordings', $this->has_recordings);
        Session::put('csv_export:filter:has_video', $this->has_video);

        $this->error_message = '';

        try {
            $callLog = new CallLogStats(
                Carbon::parse($this->start_date)->format('Y-m-d H:i:s'),
                Carbon::parse($this->end_date)->format('Y-m-d H:i:s'),
                $this->timezone,
                $this->client_number,
                $this->ani,
                $this->call_type,
                $this->agent,
                $this->min_duration,
                $this->max_duration,
                $this->keyword,
                $this->keyword_search,
                $this->sort_by,
                $this->sort_direction,
                $this->has_messages,
                $this->has_recordings,
                $this->has_video,
                $this->has_any,
                request()->user()->currentTeam->allowed_accounts,
                request()->user()->currentTeam->allowed_billing,
            );

            $this->result_count = count($callLog->results ?? []);
        } catch (Exception $e) {
            $this->result_count = 0;
            $this->error_message = 'Unable to query call log data. Please verify the data source connection is configured and accessible.';
        }

        $this->queried = true;
        $this->dispatch('saved');
    }

    public function resetFilter(): void
    {
        $this->start_date = now($this->timezone)->subHours(1)->format('Y-m-d\TH:i');
        $this->end_date = now($this->timezone)->format('Y-m-d\TH:i');
        $this->client_number = null;
        $this->ani = null;
        $this->call_type = null;
        $this->agent = null;
        $this->min_duration = null;
        $this->max_duration = null;
        $this->keyword = null;
        $this->keyword_search = null;
        $this->sort_by = 'statCallStart.Stamp';
        $this->sort_direction = 'desc';
        $this->has_any = true;
        $this->has_messages = false;
        $this->has_recordings = false;
        $this->has_video = false;
        $this->applyFilter();
    }

    public function setSorting(string $field, string $direction): void
    {
        if ($direction === 'desc') {
            $this->sort_direction = 'desc';
        } else {
            $this->sort_direction = 'asc';
        }

        if ($field === 'Duration') {
            $this->sort_by = 'CallDuration';
        } elseif ($field === 'Stamp') {
            $this->sort_by = 'statCallStart.Stamp';
        } else {
            $this->sort_by = 'statCallStart.Stamp';
        }

        Session::put('csv_export:filter:sort_by', $this->sort_by);
        Session::put('csv_export:filter:sort_direction', $this->sort_direction);
        $this->dispatch('saved');
    }

    public function exportCsv(): StreamedResponse
    {
        $callLog = new CallLogStats(
            Carbon::parse($this->start_date)->format('Y-m-d H:i:s'),
            Carbon::parse($this->end_date)->format('Y-m-d H:i:s'),
            $this->timezone,
            $this->client_number,
            $this->ani,
            $this->call_type,
            $this->agent,
            $this->min_duration,
            $this->max_duration,
            $this->keyword,
            $this->keyword_search,
            $this->sort_by,
            $this->sort_direction,
            $this->has_messages,
            $this->has_recordings,
            $this->has_video,
            $this->has_any,
            request()->user()->currentTeam->allowed_accounts,
            request()->user()->currentTeam->allowed_billing,
        );

        $results = $callLog->results ?? [];
        $ck = Helpers::callTypes();
        $st = Helpers::stationTypes();

        $filename = 'call-log-export-'.now($this->timezone)->format('Y-m-d_His').'.csv';

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
