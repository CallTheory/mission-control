<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\FaxSpoolSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The cached view of one spool source's folders that the fax status pages render from.
 *
 * Reading the spool is directory I/O, and once a source can be a remote share it is
 * network I/O with a dead server at the other end. Doing that inside a Livewire render —
 * which the mFax page did — puts that latency on a user's page load and, with an
 * unreachable source, hangs an fpm worker. Building the snapshot on the scheduler instead
 * means every viewer reads the same instant result and nobody's request touches a mount.
 *
 * Keyed per source: two Intelligent Series servers have separate folders, and a single
 * shared key would show whichever was written last as though it were both.
 */
class FaxDashboardSnapshot
{
    /**
     * Comfortably longer than the one-minute build, so a single slow or failed run never
     * blanks the page.
     */
    private const TTL_SECONDS = 180;

    public static function key(string $sourceKey): string
    {
        return "cloud-faxing:dashboard:{$sourceKey}";
    }

    /**
     * Read the spool and cache the result. Returns the snapshot it stored.
     *
     * @return array<string, mixed>
     */
    public function build(FaxSpoolSource $source): array
    {
        $provider = $source->pinned_provider->value ?? 'mfax';

        try {
            $snapshot = (new FaxSpool)->snapshot($provider, $source->key);
            $snapshot['unreachable'] = false;
        } catch (Throwable $e) {
            Log::warning("FaxDashboardSnapshot: unable to read [{$source->key}]: ".$e->getMessage());

            // A source that cannot be read is reported as such rather than as empty —
            // an empty tosend/ is the normal resting state for every server that is not
            // currently active, so the two must not look alike on the status page.
            $snapshot = ['unreachable' => true, 'error' => $e->getMessage()];
        }

        $snapshot['source_key'] = $source->key;
        $snapshot['source_name'] = $source->name;
        $snapshot['generated_at'] = now()->toIso8601String();

        Cache::put(self::key($source->key), $snapshot, self::TTL_SECONDS);

        return $snapshot;
    }

    /**
     * The cached snapshot for a source, or null when none has been built yet.
     *
     * @return array<string, mixed>|null
     */
    public function read(string $sourceKey): ?array
    {
        try {
            $snapshot = Cache::get(self::key($sourceKey));
        } catch (Throwable $e) {
            return null;
        }

        return is_array($snapshot) ? $snapshot : null;
    }
}
