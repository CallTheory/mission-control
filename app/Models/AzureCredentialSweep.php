<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One attempt at sweeping the tenant's credentials.
 *
 * The dashboard reads the newest row to decide whether what it is showing is
 * current: a collector that has stopped running would otherwise look exactly
 * like a tenant with nothing expiring.
 *
 * @property int $id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string $status
 * @property int $applications
 * @property int $service_principals
 * @property int $credentials_seen
 * @property int $credentials_added
 * @property int $credentials_removed
 * @property int $alerts_sent
 * @property string|null $error
 */
class AzureCredentialSweep extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    /**
     * A successful sweep older than this means the data on screen is stale. 25
     * hours, not 24: the sweep runs daily, and a schedule that drifts by minutes
     * must not raise a false alarm every morning.
     */
    public const STALE_AFTER_HOURS = 25;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function succeeded(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * The most recent sweep of any outcome, for reporting what happened last.
     *
     * Deliberately not named latest(): that would shadow Eloquent's own static
     * latest() query scope on this model.
     */
    public static function mostRecent(): ?self
    {
        return self::query()->orderByDesc('started_at')->first();
    }

    /**
     * The most recent sweep that actually completed, which is what the
     * credential table's freshness is measured against.
     */
    public static function mostRecentSuccessful(): ?self
    {
        return self::query()
            ->where('status', self::STATUS_SUCCESS)
            ->orderByDesc('started_at')
            ->first();
    }

    public static function isStale(): bool
    {
        $sweep = self::mostRecentSuccessful();

        return $sweep === null
            || $sweep->started_at->lt(Carbon::now()->subHours(self::STALE_AFTER_HOURS));
    }
}
