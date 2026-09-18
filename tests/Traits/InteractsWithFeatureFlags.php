<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\System\SystemFeature;
use App\Services\FeatureFlags;

/**
 * System feature flags live in the `system_features` table and are served from
 * the cache by App\Services\FeatureFlags.
 *
 * They were previously encrypted files on the default disk, which is why this
 * trait used to fake the disk: tests writing real files left features switched
 * on for the developer when they crashed. Rows in a RefreshDatabase transaction
 * cannot leak that way, so the fake is gone -- but the flag map is cached, and
 * the service memoises it per instance, so both have to be cleared whenever a
 * test changes a flag. enable/disable below do that for you.
 *
 * Note routes/web.php and routes/api.php read flags at ROUTE-REGISTRATION time,
 * so a flag enabled inside a test body does not register that feature's routes
 * for that test — such tests must hit literal URL paths rather than route()
 * names.
 */
trait InteractsWithFeatureFlags
{
    protected function enableSystemFeature(string $feature): void
    {
        $this->setSystemFeature($feature, true);
    }

    protected function disableSystemFeature(string $feature): void
    {
        $this->setSystemFeature($feature, false);
    }

    /** Clears every flag. */
    protected function disableAllSystemFeatures(): void
    {
        SystemFeature::query()->delete();

        $this->forgetSystemFeatureCache();
    }

    private function setSystemFeature(string $feature, bool $enabled): void
    {
        SystemFeature::query()->updateOrCreate(['key' => $feature], ['enabled' => $enabled]);

        $this->forgetSystemFeatureCache();
    }

    /**
     * Both layers: the cached map, and the singleton's per-request memo, which
     * would otherwise keep serving the value read before the change.
     */
    private function forgetSystemFeatureCache(): void
    {
        app(FeatureFlags::class)->flush();
    }
}
