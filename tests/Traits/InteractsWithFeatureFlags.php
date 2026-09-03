<?php

declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\Storage;

/**
 * System feature flags are encrypted files on the default disk, read by
 * App\Models\Stats\Helpers::isSystemFeatureEnabled().
 *
 * Tests must never write them to the real `storage/app` disk: a crashed test
 * leaves a system feature switched on for the developer, and the
 * Storage::deleteDirectory('feature-flags') cleanups these tests used to do
 * would delete real flag files. setUpInteractsWithFeatureFlags() is invoked
 * automatically by Laravel's setUpTraits(), so simply using this trait swaps in
 * a fake disk for the whole test.
 *
 * Note routes/web.php reads flags at ROUTE-REGISTRATION time, so a flag enabled
 * inside a test body does not register that feature's routes for that test —
 * such tests must hit literal URL paths rather than route() names.
 */
trait InteractsWithFeatureFlags
{
    protected function setUpInteractsWithFeatureFlags(): void
    {
        Storage::fake();
    }

    protected function enableSystemFeature(string $feature): void
    {
        Storage::put("feature-flags/{$feature}.flag", encrypt($feature));
    }

    protected function disableSystemFeature(string $feature): void
    {
        Storage::delete("feature-flags/{$feature}.flag");
    }

    /** Clears every flag — safe here because the disk is faked. */
    protected function disableAllSystemFeatures(): void
    {
        Storage::deleteDirectory('feature-flags');
    }
}
