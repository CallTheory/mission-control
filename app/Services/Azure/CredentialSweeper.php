<?php

declare(strict_types=1);

namespace App\Services\Azure;

use App\Enums\AzureCredentialSource;
use App\Enums\AzureCredentialType;
use App\Models\AzureCredential;
use App\Models\AzureCredentialSweep;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * The collector: one pass over the tenant, flattening every app registration and
 * service principal into one row per credential.
 *
 * Each pass records an AzureCredentialSweep row whatever the outcome, because the
 * dashboard's freshness -- and therefore its trustworthiness -- is measured from
 * the last sweep that actually finished.
 */
class CredentialSweeper
{
    public function __construct(private readonly GraphClient $graph) {}

    /**
     * Sweep the tenant and reconcile the credential table against it.
     *
     * @throws GraphException on any Graph failure, after the sweep row has been
     *                        marked failed with the reason.
     */
    public function sweep(): AzureCredentialSweep
    {
        $startedAt = Carbon::now()->utc();

        $sweep = AzureCredentialSweep::create([
            'started_at' => $startedAt,
            'status' => AzureCredentialSweep::STATUS_RUNNING,
        ]);

        $counts = [
            'applications' => 0,
            'service_principals' => 0,
            'credentials_seen' => 0,
            'credentials_added' => 0,
        ];

        try {
            foreach ($this->graph->applications() as $application) {
                $counts['applications']++;
                $this->store($application, AzureCredentialSource::Application, $startedAt, $counts);
            }

            foreach ($this->graph->servicePrincipals() as $servicePrincipal) {
                $counts['service_principals']++;
                $this->store($servicePrincipal, AzureCredentialSource::ServicePrincipal, $startedAt, $counts);
            }

            // A tenant always contains at least the watcher's own registration, so
            // an empty result is a broken read rather than an empty directory --
            // and acting on it would mark every credential removed at once.
            if ($counts['applications'] === 0) {
                throw new GraphException(
                    'Microsoft Graph returned no app registrations at all. Treating this as a '
                    .'failed read rather than an empty tenant, so nothing is marked removed.'
                );
            }

            $removed = $this->markRemoved($startedAt);

            $sweep->update([
                'status' => AzureCredentialSweep::STATUS_SUCCESS,
                'finished_at' => Carbon::now(),
                'credentials_removed' => $removed,
                ...$counts,
            ]);
        } catch (Throwable $e) {
            $sweep->update([
                'status' => AzureCredentialSweep::STATUS_FAILED,
                'finished_at' => Carbon::now(),
                // Long Graph messages carry a request id and stack of inner errors;
                // the first part is the actionable bit.
                'error' => Str::limit($e->getMessage(), 1000),
                ...$counts,
            ]);

            throw $e;
        }

        return $sweep->refresh();
    }

    /**
     * Credentials the sweep no longer saw. Kept as history and marked removed
     * rather than deleted -- and never alerted on again.
     */
    private function markRemoved(Carbon $startedAt): int
    {
        return AzureCredential::query()
            ->present()
            ->where('last_seen_utc', '<', $startedAt)
            ->update(['removed_at' => Carbon::now()]);
    }

    /**
     * Flatten one Graph object's credentials into rows.
     *
     * @param  array<string, mixed>  $object
     * @param  array<string, int>  $counts
     */
    private function store(array $object, AzureCredentialSource $source, Carbon $stamp, array &$counts): void
    {
        $objectId = $object['id'] ?? null;

        if (! is_string($objectId) || $objectId === '') {
            return;
        }

        $app = [
            'app_object_id' => $objectId,
            'app_client_id' => is_string($object['appId'] ?? null) ? $object['appId'] : '',
            // Unnamed objects exist; the row still has to be identifiable.
            'app_name' => filled($object['displayName'] ?? null)
                ? (string) $object['displayName']
                : '(unnamed)',
            'source' => $source,
        ];

        $entries = [
            AzureCredentialType::Secret->value => $object['passwordCredentials'] ?? [],
            AzureCredentialType::Certificate->value => $object['keyCredentials'] ?? [],
        ];

        // keyId is not unique within keyCredentials: one certificate is normally
        // listed twice, once with usage Sign and once with usage Verify. Both
        // entries describe the same expiry, so the first one wins and the duplicate
        // is not counted as a second credential.
        $seen = [];

        foreach ($entries as $type => $collection) {
            if (! is_array($collection)) {
                continue;
            }

            foreach ($collection as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $keyId = $entry['keyId'] ?? null;
                $endDateTime = $entry['endDateTime'] ?? null;

                // Without a keyId there is nothing stable to key the row on, and
                // without an expiry there is nothing to watch.
                if (! is_string($keyId) || $keyId === '' || ! is_string($endDateTime) || $endDateTime === '') {
                    continue;
                }

                if (isset($seen[$keyId])) {
                    continue;
                }

                $seen[$keyId] = true;
                $counts['credentials_seen']++;

                $wasCreated = $this->upsert(
                    $app,
                    AzureCredentialType::from($type),
                    $keyId,
                    $entry,
                    $endDateTime,
                    $stamp,
                );

                if ($wasCreated) {
                    $counts['credentials_added']++;
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $app
     * @param  array<string, mixed>  $entry
     * @return bool whether the row was new
     */
    private function upsert(
        array $app,
        AzureCredentialType $type,
        string $keyId,
        array $entry,
        string $endDateTime,
        Carbon $stamp,
    ): bool {
        $credential = AzureCredential::query()->firstOrNew([
            'app_object_id' => $app['app_object_id'],
            'key_id' => $keyId,
        ]);

        $wasCreated = ! $credential->exists;
        $endUtc = Carbon::parse($endDateTime)->utc();

        // A credential whose expiry moved is effectively a different credential, so
        // its alert history no longer applies: without this reset, a secret extended
        // out to 2028 would never alert again, having already alerted at 3 days.
        if (! $wasCreated && ! $credential->end_utc->equalTo($endUtc)) {
            $credential->alerted_threshold = null;
        }

        $credential->fill([
            ...$app,
            'cred_type' => $type,
            'cred_name' => filled($entry['displayName'] ?? null) ? (string) $entry['displayName'] : null,
            'hint' => $this->hint($type, $entry),
            'start_utc' => filled($entry['startDateTime'] ?? null)
                ? Carbon::parse((string) $entry['startDateTime'])->utc()
                : null,
            'end_utc' => $endUtc,
            'last_seen_utc' => $stamp,
            // A credential Azure is returning again is present again, whatever an
            // earlier sweep concluded.
            'removed_at' => null,
        ]);

        $credential->save();

        return $wasCreated;
    }

    /**
     * Something to tell two unnamed credentials apart: Azure gives secrets a
     * three-character hint, and certificates a base64 thumbprint.
     *
     * @param  array<string, mixed>  $entry
     */
    private function hint(AzureCredentialType $type, array $entry): ?string
    {
        if ($type === AzureCredentialType::Secret) {
            return filled($entry['hint'] ?? null) ? (string) $entry['hint'] : null;
        }

        $identifier = $entry['customKeyIdentifier'] ?? null;

        if (! is_string($identifier) || $identifier === '') {
            return null;
        }

        $decoded = base64_decode($identifier, true);

        // Graph sends the thumbprint base64-encoded; hex is what the portal and
        // every certificate tool show.
        return $decoded === false
            ? Str::limit($identifier, 64, '')
            : strtoupper(bin2hex($decoded));
    }
}
