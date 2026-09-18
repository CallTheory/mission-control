<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\System\SystemFeature;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * System feature flags, backed by the `system_features` table and served from
 * the cache (Redis in every configured environment).
 *
 * The whole flag set is cached under one key rather than a key per flag. A
 * request commonly checks several flags -- routes/api.php checks three before
 * it has even registered a route -- and one map means one round trip for all of
 * them instead of one each. A per-instance memo collapses repeat reads within a
 * single request to zero.
 *
 * Every lookup degrades rather than throws, in this order: cache, then the
 * table, then "everything disabled". That last step matters more than it looks.
 * routes/api.php and routes/web.php read flags at route-REGISTRATION time, so
 * an exception here would break `artisan` itself -- including the very
 * `artisan migrate` that creates this table on a fresh install, and any console
 * command run while Redis is down.
 */
class FeatureFlags
{
    public const CACHE_KEY = 'system-features';

    /**
     * Resolved flag map for this request, or null before the first read.
     *
     * @var array<string, bool>|null
     */
    private ?array $memo = null;

    public function enabled(string $key): bool
    {
        return $this->all()[$key] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        try {
            return $this->memo = Cache::rememberForever(
                self::CACHE_KEY,
                fn (): array => $this->fromDatabase()
            );
        } catch (Throwable) {
            // Cache unreachable: fall through to the table for this request,
            // without memoising a result the cache never saw.
            return $this->fromDatabase();
        }
    }

    public function enable(string $key): void
    {
        $this->write($key, true);
    }

    public function disable(string $key): void
    {
        $this->write($key, false);
    }

    /**
     * Flip a flag and return its new state.
     */
    public function toggle(string $key): bool
    {
        $next = ! $this->enabled($key);

        $this->write($key, $next);

        return $next;
    }

    /**
     * Drop the cached map. Writes call this; a deploy or an out-of-band edit to
     * the table wants `artisan cache:forget system-features`.
     */
    public function flush(): void
    {
        $this->memo = null;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable) {
            // Nothing to do: a cache we cannot reach holds nothing to clear,
            // and reads fall back to the table.
        }
    }

    private function write(string $key, bool $enabled): void
    {
        SystemFeature::query()->updateOrCreate(['key' => $key], ['enabled' => $enabled]);

        $this->flush();
    }

    /**
     * @return array<string, bool>
     */
    private function fromDatabase(): array
    {
        try {
            return SystemFeature::query()
                ->pluck('enabled', 'key')
                ->map(fn ($enabled): bool => (bool) $enabled)
                ->all();
        } catch (Throwable) {
            // No database, or no table yet on a fresh install mid-migration.
            return [];
        }
    }
}
