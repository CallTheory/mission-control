<?php

namespace App\Livewire\Analytics;

use App\Models\Stats\Messages\Keywords;
use App\Models\Stats\Agents\Listing;
use App\Models\Stats\Calls\CallLog as CallLogStats;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Carbon\Carbon;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

class CallLog extends Component
{
    use WithPagination;

    #[Url]
    public int $page;

    public array $ck, $st, $agents;

    public array $sql_params = [];
    public string $sql_code = '';

    public string|null $start_date, $end_date, $client_number, $ani,
        $call_type, $agent, $min_duration, $max_duration,
        $keyword, $keyword_search;

    public bool $has_any, $has_messages,
        $has_recordings, $has_video;


    #[Locked]
    public string $sort_by = 'statCallStart.Stamp', $sort_direction = 'desc';

    public string $timezone = 'UTC';

    public array $keywords = [];

    public function mount(): void
    {

        try{
            $agents = new Listing;
            $this->agents = $agents->results;
        }
        catch(Exception $e)
        {
            $this->agents = [];
        }

        try{
            $keywords = new Keywords;
            $this->keywords = $keywords->results;
        }
        catch(Exception $e){
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

        $this->start_date = Session::get('call_log:filter:start_date', now($this->timezone)->subHours()->format('Y-m-d\TH:i'));
        $this->end_date = Session::get('call_log:filter:end_date', now($this->timezone)->format('Y-m-d\TH:i'));
        $this->client_number = Session::get('call_log:filter:client_number');
        $this->ani = Session::get('call_log:filter:ani');
        $this->call_type = Session::get('call_log:filter:call_type');
        $this->agent = Session::get('call_log:filter:agent');
        $this->min_duration = Session::get('call_log:filter:min_duration');
        $this->max_duration = Session::get('call_log:filter:max_duration');
        $this->keyword = Session::get('call_log:filter:keyword');
        $this->keyword_search = Session::get('call_log:filter:keyword_search');
        $this->sort_by = Session::get('call_log:filter:sort_by', 'statCallStart.Stamp');
        $this->sort_direction = Session::get('call_log:filter:sort_direction', 'desc');

        $this->has_any = Session::get('call_log:filter:has_any', true);
        $this->has_messages = Session::get('call_log:filter:has_messages', false);
        $this->has_recordings = Session::get('call_log:filter:has_recordings', false);
        $this->has_video = Session::get('call_log:filter:has_video', false);

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
        $this->resetPage();
        $this->applyFilter();
        $this->dispatch('saved');
    }

    public function setSorting($field, $direction): void
    {

        if($direction === 'desc'){
            $this->sort_direction = 'desc';
        }
        else{
            $this->sort_direction = 'asc';
        }

        if($field === 'Duration'){
            $this->sort_by = 'CallDuration';
        }
        elseif($field === 'Stamp'){
            $this->sort_by = 'statCallStart.Stamp';
        }
        else{
            $this->sort_by = 'statCallStart.Stamp';
        }
        Session::put('call_log:filter:sort_by', $this->sort_by);
        Session::put('call_log:filter:sort_direction', $this->sort_direction);
        $this->dispatch('saved');

    }
    public function applyFilter(): void
    {
        Session::put('call_log:filter:start_date', $this->start_date);
        Session::put('call_log:filter:end_date', $this->end_date);
        Session::put('call_log:filter:client_number', $this->client_number);
        Session::put('call_log:filter:ani', $this->ani);
        Session::put('call_log:filter:call_type', $this->call_type);
        Session::put('call_log:filter:agent', $this->agent);
        Session::put('call_log:filter:min_duration', $this->min_duration);
        Session::put('call_log:filter:max_duration', $this->max_duration);
        Session::put('call_log:filter:keyword', $this->keyword);
        Session::put('call_log:filter:keyword_search', $this->keyword_search);
        Session::put('call_log:filter:sort_by', $this->sort_by);
        Session::put('call_log:filter:sort_direction', $this->sort_direction);

        Session::put('call_log:filter:has_any', $this->has_any);
        Session::put('call_log:filter:has_messages', $this->has_messages);
        Session::put('call_log:filter:has_recordings', $this->has_recordings);
        Session::put('call_log:filter:has_video', $this->has_video);

        $this->gotoPage($this->getPage());
        $this->dispatch('saved');
    }

    public function filterCalls(): LengthAwarePaginator{

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

            $callLogArray = $callLog->results ?? [];
            $this->sql_code = $callLog->tsql() ?? '';
            $this->sql_params = $callLog->parameters ?? [];

        } catch (Exception $e) {
            $callLogArray = [];
        }

        $per_page = 50;
        $starting_point = ( $this->getPage()  * $per_page) - $per_page;

        return new LengthAwarePaginator(
            array_slice($callLogArray, $starting_point, $per_page),
            count($callLogArray),
            $per_page
        );
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm">
           Loading call log...one moment, please.
        </div>
        HTML;
    }

    #[On('saved')]
    public function render(): View
    {
        $call_log = $this->filterCalls();
        return view('livewire.analytics.call-log', ['call_log' => $call_log]);
    }
}
