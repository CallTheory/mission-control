<?php

namespace App\Livewire\Dashboard;

use App\Models\Stats\Aggregate\AbandonRate;
use App\Models\Stats\Aggregate\AgentAbandon;
use App\Models\Stats\Aggregate\AnswerTime;
use App\Models\Stats\Aggregate\DiscTime;
use App\Models\Stats\Aggregate\GreetingHangup;
use App\Models\Stats\Aggregate\Secretarial;
use App\Models\Stats\Aggregate\SystemAbandon;
use App\Models\Stats\Aggregate\TalkTime;
use App\Models\Stats\Aggregate\TotalAbandon;
use App\Models\System\Settings;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Statistic extends Component
{
    public float $metric = 0.0;

    public string $title = '';

    public string $desc = '';

    public string $tail = '';

    public int $rounding = 0;

    public string $type = '';

    public function update(string $period = null ): void
    {
        $settings = Settings::firstOrFail();

        if (! is_null($settings)) {
            $switchTimezone = $settings->switch_data_timezone ?? 'UTC';
        } else {
            $switchTimezone = 'UTC';
        }

        $startDate = Carbon::now($switchTimezone)->subHours(24)->format('Y-m-d H:i:s');
        $endDate = Carbon::now($switchTimezone)->format('Y-m-d H:i:s');

        if(isset($period) && $period === 'lastHour'){
            $startDate = Carbon::now($switchTimezone)->subHours(1)->format('Y-m-d H:i:s');
            $endDate = Carbon::now($switchTimezone)->format('Y-m-d H:i:s');
        }
        elseif(isset($period) && $period === 'sinceMidnight'){
            $startDate = Carbon::today($switchTimezone)->format('Y-m-d H:i:s');
            $endDate = Carbon::now($switchTimezone)->format('Y-m-d H:i:s');
        }

        $allowed_accounts = request()->user()->currentTeam->allowed_accounts ?? '';
        $allowed_billing = request()->user()->currentTeam->allowed_billing ?? '';

        switch ($this->type) {
            case 'secretarial_calls':
                try {
                    $secretarial_calls = new Secretarial([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }

                $this->metric = $secretarial_calls->total ?? 0.0;
                break;

            case 'average_answer_time':
                try {
                    $average_answer_time = new AnswerTime([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }

                $this->metric = $average_answer_time->average ?? 0.0;
                break;
            case 'average_talk_time':
                try {
                    $average_talk_time = new TalkTime([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $average_talk_time->average ?? 0.0;
                break;

            case 'greeting_hangups':
                try {
                    $greeting_hangups = new GreetingHangup([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $greeting_hangups->total ?? 0.0;
                break;

            case 'total_abandons':
                try {
                    $total_abandons = new TotalAbandon([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $total_abandons->total ?? 0.0;
                break;

            case 'system_abandons':
                try {
                    $system_abandons = new SystemAbandon([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $system_abandons->total ?? 0.0;
                break;

            case 'agent_abandons':
                try {
                    $agent_abandons = new AgentAbandon([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $agent_abandons->total ?? 0.0;
                break;

            case 'average_abandon_rate':
                try {
                    $average_abandon_rate = new AbandonRate([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $average_abandon_rate->average ?? 0.0;
                break;

            case 'average_disc_time':
                try {
                    $average_disc_time = new DiscTime([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'allowed_accounts' => $allowed_accounts,
                        'allowed_billing' => $allowed_billing,
                    ]);
                } catch (Exception $e) {
                }
                $this->metric = $average_disc_time->average ?? 0.0;
                break;

            default:
                break;
        }
    }

    public function mount(string $type): void
    {
        $this->type = $type;
        $this->widgetSetup();
    }

    public function render(): View
    {
        return view('livewire.dashboard.statistic');
    }

    public function widgetSetup(): void
    {
        switch ($this->type) {
            case 'secretarial_calls':
                $this->metric = 0.0;
                $this->title = 'Secretarial Calls';
                $this->desc = 'Live Inbound Calls';
                $this->tail = '';
                $this->rounding = 0;
                break;
            case 'average_answer_time':
                $this->metric = 0.0;
                $this->title = 'Average Time To Answer';
                $this->desc = 'Average Speed of Answer (ASA)';
                $this->tail = 's';
                $this->rounding = 1;
                break;
            case 'average_talk_time':
                $this->metric = 0.0;
                $this->title = 'Average Talk Time';
                $this->desc = 'Inbound Secretarial Talk Time';
                $this->tail = 's';
                $this->rounding = 1;
                break;
            case 'greeting_hangups':
                $this->metric = 0.0;
                $this->title = 'Greeting Hangups';
                $this->desc = 'Caller hung up during Announcement call';
                $this->tail = '';
                $this->rounding = 0;
                break;

            case 'total_abandons':
                $this->metric = 0.0;
                $this->title = 'Total Abandons';
                $this->desc = 'All system and agent abandons';
                $this->tail = '';
                $this->rounding = 0;
                break;

            case 'system_abandons':
                $this->metric = 0.0;
                $this->title = 'System Abandons';
                $this->desc = 'Abandon In System Queue';
                $this->tail = '';
                $this->rounding = 0;
                break;
            case 'agent_abandons':
                $this->metric = 0.0;
                $this->title = 'Agent Abandons';
                $this->desc = 'Abandon After Assigned';
                $this->tail = '';
                $this->rounding = 0;
                break;
            case 'average_abandon_rate':
                $this->metric = 0.0;
                $this->title = 'Abandon Rate';
                $this->desc = 'Total Abandon Rate';
                $this->tail = '%';
                $this->rounding = 1;
                break;
            case 'average_disc_time':
                $this->metric = 0.0;
                $this->title = 'Average Disconnect Time';
                $this->desc = 'After Call Work (ACW)';
                $this->tail = 's';
                $this->rounding = 1;
                break;
            default:
                break;
        }
    }
}
