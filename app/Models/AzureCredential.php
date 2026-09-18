<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AzureCredentialSource;
use App\Enums\AzureCredentialStatus;
use App\Enums\AzureCredentialType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One client secret or certificate on an Entra app registration or service
 * principal, as of the last sweep.
 *
 * Nothing here is a secret value: Microsoft Graph returns credential metadata
 * only, never the secret itself.
 *
 * @property int $id
 * @property string $key_id
 * @property string $app_object_id
 * @property string $app_client_id
 * @property string $app_name
 * @property AzureCredentialSource $source
 * @property AzureCredentialType $cred_type
 * @property string|null $cred_name
 * @property string|null $hint
 * @property Carbon|null $start_utc
 * @property Carbon $end_utc
 * @property Carbon $last_seen_utc
 * @property Carbon|null $removed_at
 * @property bool $acknowledged
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by
 * @property int|null $alerted_threshold
 */
class AzureCredential extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'source' => AzureCredentialSource::class,
            'cred_type' => AzureCredentialType::class,
            'start_utc' => 'datetime',
            'end_utc' => 'datetime',
            'last_seen_utc' => 'datetime',
            'removed_at' => 'datetime',
            'acknowledged' => 'boolean',
            'acknowledged_at' => 'datetime',
            'alerted_threshold' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Credentials the last completed sweep still saw. Rows Azure has stopped
     * returning are kept for history but are not the tenant's current state.
     */
    public function scopePresent(Builder $query): Builder
    {
        return $query->whereNull('removed_at');
    }

    public function scopeRemoved(Builder $query): Builder
    {
        return $query->whereNotNull('removed_at');
    }

    /**
     * Credentials in one of the red/yellow/green bands.
     *
     * The window boundaries are one day wider than the threshold they name, so this
     * agrees with daysRemaining(): a credential 14.9 days out reads as "14 days
     * left" in the table and has to be Critical in the filter as well.
     */
    public function scopeStatus(Builder $query, AzureCredentialStatus $status): Builder
    {
        $now = Carbon::now()->utc();
        $critical = $now->copy()->addDays(AzureCredentialStatus::CRITICAL_DAYS + 1);
        $warning = $now->copy()->addDays(AzureCredentialStatus::WARNING_DAYS + 1);

        return match ($status) {
            AzureCredentialStatus::Expired => $query->where('end_utc', '<', $now),
            AzureCredentialStatus::Critical => $query
                ->where('end_utc', '>=', $now)
                ->where('end_utc', '<', $critical),
            AzureCredentialStatus::Warning => $query
                ->where('end_utc', '>=', $critical)
                ->where('end_utc', '<', $warning),
            AzureCredentialStatus::Healthy => $query->where('end_utc', '>=', $warning),
        };
    }

    /**
     * Not yet expired, but inside $days. Same boundary convention as scopeStatus().
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        $now = Carbon::now()->utc();

        return $query
            ->where('end_utc', '>=', $now)
            ->where('end_utc', '<', $now->copy()->addDays($days + 1));
    }

    /**
     * Whole days until expiry, negative once expired.
     *
     * Computed in UTC because every timestamp Graph returns is UTC; the UI is
     * free to render the date in local time, but the arithmetic is not.
     */
    public function daysRemaining(?Carbon $now = null): int
    {
        $now = ($now ?? Carbon::now())->utc();

        return (int) floor($now->diffInDays($this->end_utc->copy()->utc(), false));
    }

    public function status(?Carbon $now = null): AzureCredentialStatus
    {
        return AzureCredentialStatus::fromDaysRemaining($this->daysRemaining($now));
    }

    public function hasExpired(?Carbon $now = null): bool
    {
        return $this->end_utc->copy()->utc()->isBefore(($now ?? Carbon::now())->utc());
    }

    /**
     * Something distinguishable to show in the credential column.
     *
     * A blank displayName is the norm in Azure, so fall back to the secret hint
     * and then to the tail of the keyId -- two rows on the same app with no names
     * still have to be tellable apart.
     */
    public function label(): string
    {
        if (filled($this->cred_name)) {
            return $this->cred_name;
        }

        if (filled($this->hint)) {
            return $this->cred_type === AzureCredentialType::Certificate
                ? 'Thumbprint '.$this->hint
                : $this->hint.'...';
        }

        return 'Key ...'.substr($this->key_id, -6);
    }

    /**
     * Deep link to the app's credentials blade in the Entra portal.
     */
    public function portalUrl(): string
    {
        return 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps'
            .'/ApplicationMenuBlade/~/Credentials/appId/'.$this->app_client_id;
    }
}
