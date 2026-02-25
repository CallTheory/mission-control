<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $team_id
 * @property Team $team
 * @property string|null $name
 * @property string|null $client_number
 * @property string|null $billing_code
 * @property array|null $recipients
 * @property string|null $subject
 * @property string|null $schedule_type
 * @property string|null $schedule_time
 * @property int|null $schedule_day_of_week
 * @property int|null $schedule_day_of_month
 * @property bool $include_transcription
 * @property bool $include_call_metadata
 * @property bool $enabled
 * @property \Carbon\Carbon|null $last_run_at
 * @property \Carbon\Carbon|null $next_run_at
 * @property string|null $timezone
 */
class VoicemailDigest extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'voicemail_digests';

    protected $fillable = [
        'team_id',
        'name',
        'client_number',
        'billing_code',
        'recipients',
        'subject',
        'schedule_type',
        'schedule_time',
        'schedule_day_of_week',
        'schedule_day_of_month',
        'include_transcription',
        'include_call_metadata',
        'enabled',
        'last_run_at',
        'next_run_at',
        'timezone',
    ];

    protected $casts = [
        'recipients' => 'array',
        'include_transcription' => 'boolean',
        'include_call_metadata' => 'boolean',
        'enabled' => 'boolean',
        'last_run_at' => 'datetime',
        'next_run_at' => 'datetime',
        'schedule_day_of_week' => 'integer',
        'schedule_day_of_month' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Calculate the next run time based on schedule configuration.
     */
    public function calculateNextRunAt(?Carbon $from = null): Carbon
    {
        $from = $from ?? Carbon::now($this->timezone);
        $time = $this->schedule_time ? Carbon::parse($this->schedule_time, $this->timezone) : $from->copy();

        return match ($this->schedule_type) {
            'immediate' => $from->copy()->addMinute(),
            'hourly' => $this->calculateNextHourly($from),
            'daily' => $this->calculateNextDaily($from, $time),
            'weekly' => $this->calculateNextWeekly($from, $time),
            'monthly' => $this->calculateNextMonthly($from, $time),
            default => $from->addHour(),
        };
    }

    private function calculateNextHourly(Carbon $from): Carbon
    {
        return $from->copy()->addHour()->startOfHour();
    }

    private function calculateNextDaily(Carbon $from, Carbon $time): Carbon
    {
        $next = $from->copy()->setTimeFrom($time);

        if ($next->lte($from)) {
            $next->addDay();
        }

        return $next;
    }

    private function calculateNextWeekly(Carbon $from, Carbon $time): Carbon
    {
        $dayOfWeek = $this->schedule_day_of_week ?? 0;
        $next = $from->copy()->next($dayOfWeek)->setTimeFrom($time);

        if ($from->dayOfWeek === $dayOfWeek && $from->lt($from->copy()->setTimeFrom($time))) {
            $next = $from->copy()->setTimeFrom($time);
        }

        return $next;
    }

    private function calculateNextMonthly(Carbon $from, Carbon $time): Carbon
    {
        $dayOfMonth = $this->schedule_day_of_month ?? 1;
        $next = $from->copy()->day($dayOfMonth)->setTimeFrom($time);

        if ($next->lte($from)) {
            $next->addMonth();
        }

        // Handle months with fewer days than the target day
        if ($next->day !== $dayOfMonth) {
            $next->day($dayOfMonth);
        }

        return $next;
    }

    /**
     * Get the date range for fetching recordings based on schedule type.
     */
    public function getDateRange(?Carbon $endDate = null): array
    {
        $end = $endDate ?? Carbon::now($this->timezone);

        $start = match ($this->schedule_type) {
            'immediate' => $this->last_run_at?->copy() ?? $end->copy()->subHour(),
            'hourly' => $end->copy()->subHour(),
            'daily' => $end->copy()->subDay(),
            'weekly' => $end->copy()->subWeek(),
            'monthly' => $end->copy()->subMonth(),
            default => $end->copy()->subDay(),
        };

        return [$start, $end];
    }

    /**
     * Check if this schedule is an immediate type.
     */
    public function isImmediate(): bool
    {
        return $this->schedule_type === 'immediate';
    }

    /**
     * Check if this schedule is due to run.
     */
    public function isDue(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if (! $this->next_run_at) {
            return true;
        }

        return Carbon::now()->gte($this->next_run_at);
    }
}
