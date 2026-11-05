<?php

namespace App\Livewire\Dashboard;

use App\Enums\DashboardTimeframe;
use App\Models\Stats\Aggregate\AbandonRate;
use App\Models\Stats\Aggregate\AgentAbandon;
use App\Models\Stats\Aggregate\AnswerTime;
use App\Models\Stats\Aggregate\DiscTime;
use App\Models\Stats\Aggregate\GreetingHangup;
use App\Models\Stats\Aggregate\Secretarial;
use App\Models\Stats\Aggregate\SystemAbandon;
use App\Models\Stats\Aggregate\TalkTime;
use App\Models\Stats\Aggregate\TotalAbandon;
use App\Models\Stats\Stat;
use App\Models\System\Settings;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class Statistic extends Component
{
    public float $metric = 0.0;

    public string $title = '';

    public string $desc = '';

    public string $tail = '';

    public int $rounding = 0;

    public string $type = '';

    /**
     * Each tile type, the stat that answers it, and the result field holding the number.
     *
     * @var array<string, array{class-string<Stat>, string}>
     */
    private const STATS = [
        'secretarial_calls' => [Secretarial::class, 'total'],
        'average_answer_time' => [AnswerTime::class, 'average'],
        'average_talk_time' => [TalkTime::class, 'average'],
        'greeting_hangups' => [GreetingHangup::class, 'total'],
        'total_abandons' => [TotalAbandon::class, 'total'],
        'system_abandons' => [SystemAbandon::class, 'total'],
        'agent_abandons' => [AgentAbandon::class, 'total'],
        'average_abandon_rate' => [AbandonRate::class, 'average'],
        'average_disc_time' => [DiscTime::class, 'average'],
    ];

    /**
     * Recalculate the metric. The window is read from the viewer's saved
     * preference rather than passed in from the markup, so the header select
     * can refresh every widget over the wire instead of waiting for a reload.
     */
    #[On('saved')]
    public function update(?string $period = null): void
    {
        if (! array_key_exists($this->type, self::STATS)) {
            return;
        }

        $timeframe = DashboardTimeframe::fromStored($period ?? request()->user()?->dashboard_timeframe);
        $allowed_accounts = request()->user()->currentTeam->allowed_accounts ?? '';
        $allowed_billing = request()->user()->currentTeam->allowed_billing ?? '';

        $seconds = (int) config('dashboard.cache_seconds');
        $key = self::cacheKey($this->type, $timeframe, $allowed_accounts, $allowed_billing);

        // The cache saves the Intelligent Series server from repeat queries; it is not
        // needed to answer one. If the cache store is down, query directly rather than
        // blank the dashboard -- so each half is guarded on its own, and a failed write
        // never costs a second query.
        if ($seconds > 0) {
            try {
                $cached = Cache::get($key);
            } catch (Throwable) {
                $cached = null;
            }

            if ($cached !== null) {
                $this->metric = (float) $cached;

                return;
            }
        }

        $metric = $this->measure($timeframe, $allowed_accounts, $allowed_billing);

        // A failed query is not cached: it is retried on the next poll, not served for the TTL.
        if ($seconds > 0 && $metric !== null) {
            try {
                Cache::put($key, $metric, $seconds);
            } catch (Throwable) {
            }
        }

        $this->metric = $metric ?? 0.0;
    }

    /**
     * One result per question, shared by every viewer who asks it. Keyed on the
     * timeframe rather than its exact start and end, which move on every poll and so
     * would never repeat.
     */
    public static function cacheKey(string $type, DashboardTimeframe $timeframe, string $allowed_accounts, string $allowed_billing): string
    {
        return 'dashboard:statistic:'.$type.':'.$timeframe->value.':'.md5($allowed_accounts."\0".$allowed_billing);
    }

    /**
     * Query the stat behind this tile. Null when the query fails.
     */
    private function measure(DashboardTimeframe $timeframe, string $allowed_accounts, string $allowed_billing): ?float
    {
        $settings = Settings::firstOrFail();
        $switchTimezone = $settings->switch_data_timezone ?? 'UTC';

        [$startDate, $endDate] = $timeframe->queryRange($switchTimezone);
        [$class, $field] = self::STATS[$this->type];

        try {
            $stat = new $class([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'allowed_accounts' => $allowed_accounts,
                'allowed_billing' => $allowed_billing,
            ]);
        } catch (Exception) {
            return null;
        }

        return (float) ($stat->{$field} ?? 0.0);
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
