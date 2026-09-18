<?php

declare(strict_types=1);

namespace App\Services\Azure;

use App\Mail\AzureCredentialExpiryAlert;
use App\Models\AzureCredential;
use App\Models\AzureCredentialSweep;
use App\Models\System\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Sends one alert per threshold a credential crosses, and no more.
 *
 * The idempotency lives in `azure_credentials.alerted_threshold`: it holds the
 * smallest threshold already reported for that credential, so a secret sitting at
 * 12 days is alerted once when it crosses 14 and then stays quiet until it reaches
 * 3. Without that, a daily sweep means a daily email for weeks, and the alert
 * stops being read long before the secret actually expires.
 */
class ExpiryAlerter
{
    /**
     * Days-remaining marks, descending. 0 means expiry itself.
     */
    public const THRESHOLDS = [30, 14, 3, 0];

    /**
     * Alert on everything that has newly crossed a threshold.
     *
     * @return int the number of credentials reported
     */
    public function run(?AzureCredentialSweep $sweep = null, ?Carbon $now = null): int
    {
        $settings = Settings::first();
        $recipients = self::recipients($settings?->azure_tokens_alert_recipients);

        // With alerting off or unaddressed, thresholds are deliberately NOT marked:
        // turning it on later should report what is already overdue, not start from
        // silence because the sweeps ran first.
        if ($settings === null || ! $settings->azure_tokens_alert_enabled || $recipients === []) {
            return 0;
        }

        $crossings = $this->crossings($now ?? Carbon::now());

        if ($crossings === []) {
            return 0;
        }

        Mail::queue(new AzureCredentialExpiryAlert(
            array_map(fn (array $crossing): array => $crossing['payload'], $crossings),
            $recipients,
        ));

        foreach ($crossings as $crossing) {
            $crossing['credential']->forceFill([
                'alerted_threshold' => $crossing['threshold'],
            ])->save();
        }

        $sweep?->increment('alerts_sent', count($crossings));

        return count($crossings);
    }

    /**
     * Credentials that have moved past a threshold they were not reported at.
     *
     * Acknowledged credentials are skipped, as are ones Azure no longer returns:
     * a deleted secret is not an expiring secret.
     *
     * @return array<int, array{credential: AzureCredential, threshold: int, payload: array<string, mixed>}>
     */
    private function crossings(Carbon $now): array
    {
        $candidates = AzureCredential::query()
            ->present()
            ->where('acknowledged', false)
            ->where('end_utc', '<=', $now->copy()->utc()->addDays(self::THRESHOLDS[0]))
            ->orderBy('end_utc')
            ->get();

        $crossings = [];

        foreach ($candidates as $credential) {
            $days = $credential->daysRemaining($now);
            $threshold = self::thresholdFor($days);

            if ($threshold === null) {
                continue;
            }

            // Already reported at this threshold or a tighter one.
            if ($credential->alerted_threshold !== null && $credential->alerted_threshold <= $threshold) {
                continue;
            }

            $crossings[] = [
                'credential' => $credential,
                'threshold' => $threshold,
                'payload' => [
                    'app_name' => $credential->app_name,
                    'app_client_id' => $credential->app_client_id,
                    'source' => $credential->source->label(),
                    'type' => $credential->cred_type->label(),
                    'credential' => $credential->label(),
                    'expires_at' => $credential->end_utc->toDayDateTimeString().' UTC',
                    'days_remaining' => $days,
                    'status' => $credential->status($now)->label(),
                    'portal_url' => $credential->portalUrl(),
                ],
            ];
        }

        return $crossings;
    }

    /**
     * The tightest threshold a days-remaining value has reached, or null when it
     * has reached none of them.
     */
    public static function thresholdFor(int $days): ?int
    {
        $crossed = null;

        foreach (self::THRESHOLDS as $threshold) {
            if ($days <= $threshold) {
                $crossed = $threshold;
            }
        }

        // A negative value (already expired) falls through every threshold and
        // lands on 0, which is the expiry alert.
        return $crossed;
    }

    /**
     * Split the stored recipient list, which administrators fill in with commas,
     * newlines or both.
     *
     * @return array<int, string>
     */
    public static function recipients(?string $stored): array
    {
        if (blank($stored)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[,;\s]+/', $stored) ?: []),
            fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }
}
