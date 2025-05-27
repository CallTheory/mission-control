<?php

namespace App\Livewire;

use App\Models\Stats\Agents\Agent;
use App\Models\Stats\Agents\AgentTracker;
use Carbon\Carbon;
use Exception;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use App\Models\Stats\Agents\AgentCalls;
use Illuminate\Support\Collection;

class PersonalDashboard extends Component
{
    public array $station_types = [];
    public Collection $activity_stream;
    public array $agent_tracker_types = [];
    public string $switch_timezone = 'UTC';
    public array $user_details = [];
    public array $agent_details = [];
    public array $agent_tracker = [];

    public array $agent_calls = [];

    #[On('saved')]
    public function mount($user = null): void
    {
        if(!$user){
            $user = auth()->user();
        }

        $settings = Settings::first();
        $this->station_types = Helpers::stationTypes();
        $this->agent_tracker_types = Helpers::agentTrackerTypes();
        $this->switch_timezone = $settings->switch_data_timezone ?? 'UTC';

        $this->user_details = $user->toArray();
        $this->user_details['teams'] = $user->allTeams()->toArray();

        foreach($this->user_details['teams'] as $key => $team){
            $this->user_details['teams'][$key]['role'] = $user->teamRole($user->currentTeam)->key;
            $this->user_details['teams'][$key]['permissions'] = $user->teamPermissions($user->currentTeam)[0];
        }

        if($user->dashboard_timeframe === "lastHour"){
            $activity_start = Carbon::now($this->switch_timezone)->timezone($user->timezone)->subHour();
            $activity_end = Carbon::now($this->switch_timezone)->timezone($user->timezone);
        }
        elseif($user->dashboard_timeframe === "sinceMidnight"){
            $activity_start = Carbon::today($this->switch_timezone)->timezone($user->timezone);
            $activity_end = Carbon::now($this->switch_timezone)->timezone($user->timezone);
        }
        else{
            $activity_start = Carbon::now($this->switch_timezone)->timezone($user->timezone)->subHours(24);
            $activity_end = Carbon::now($this->switch_timezone)->timezone($user->timezone);
        }

        if($user->agtId){
            try{
                $a = new Agent(['agtId' => $user->agtId]);

               if($a->results[0]){
                   $this->agent_details = (array)$a->results[0];
               }
            }
            catch(Exception $e){
                $this->agent_details = [];
            }

            try{
                $c = new AgentCalls(['agtId' => $user->agtId, 'agtId2' => $user->agtId, 'agtId3' => $user->agtId,//$user->agtId
                    'start_date' => $activity_start->format('Y-m-d H:i:s'),
                    'end_date' => $activity_end->format('Y-m-d H:i:s')
                ]);

                if($c->results[0]){
                    $this->agent_calls = $c->results;
                }
            }
            catch(Exception $e){
                $this->agent_calls = [];
            }

            try{
                $t = new AgentTracker([
                    'agtId' => $user->agtId, //$user->agtId,
                    'start_date' => $activity_start->format('Y-m-d H:i:s'),
                    'end_date' => $activity_end->format('Y-m-d H:i:s')
                ]);
                if($t->results[0]){
                    $this->agent_tracker = $t->results;
                }
            }
            catch(Exception $e){
                $this->agent_tracker = [];
            }

           $this->activity_stream = collect(array_merge($this->agent_calls, $this->agent_tracker))->sortByDesc('Stamp');

        }
        else{
            $this->activity_stream = collect();
        }
    }
    public function render(): View
    {
        return view('livewire.personal-dashboard');
    }
}
