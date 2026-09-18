<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Models\Stats\Helpers;
use App\Models\System\SystemFeature;
use App\Services\FeatureFlags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FeatureFlagsTest extends TestCase
{
    use RefreshDatabase;

    private function flags(): FeatureFlags
    {
        return app(FeatureFlags::class);
    }

    public function test_an_unknown_flag_reads_as_disabled(): void
    {
        $this->assertFalse($this->flags()->enabled('no-such-feature'));
    }

    public function test_enable_disable_and_toggle_round_trip(): void
    {
        $flags = $this->flags();

        $flags->enable('board-check');
        $this->assertTrue($flags->enabled('board-check'));

        $flags->disable('board-check');
        $this->assertFalse($flags->enabled('board-check'));

        $this->assertTrue($flags->toggle('board-check'));
        $this->assertFalse($flags->toggle('board-check'));
    }

    public function test_the_helper_seam_still_answers_for_every_read_site(): void
    {
        $this->flags()->enable('wctp-gateway');

        $this->assertTrue(Helpers::isSystemFeatureEnabled('wctp-gateway'));
        $this->assertFalse(Helpers::isSystemFeatureEnabled('transcription'));
    }

    public function test_reads_are_served_from_the_cache(): void
    {
        $this->flags()->enable('csv-export');

        // A write invalidates; the map is cached on the next read.
        $this->assertFalse(Cache::has(FeatureFlags::CACHE_KEY));
        $this->assertTrue($this->flags()->enabled('csv-export'));
        $this->assertTrue(Cache::has(FeatureFlags::CACHE_KEY));

        // Write straight to the table, behind the service's back.
        SystemFeature::query()->where('key', 'csv-export')->update(['enabled' => false]);

        // A fresh instance still reads the cached map, which proves the table is
        // not being hit on every check.
        $this->assertTrue((new FeatureFlags)->enabled('csv-export'));

        $this->flags()->flush();

        $this->assertFalse((new FeatureFlags)->enabled('csv-export'));
    }

    public function test_a_write_invalidates_the_cache(): void
    {
        $this->flags()->enable('mcp-server');
        $this->assertTrue((new FeatureFlags)->enabled('mcp-server'));

        $this->flags()->disable('mcp-server');
        $this->assertFalse((new FeatureFlags)->enabled('mcp-server'));
    }

    public function test_a_missing_table_reads_as_disabled_rather_than_throwing(): void
    {
        // routes/api.php checks flags at route-registration time, so this runs
        // on a fresh install before `artisan migrate` has created the table.
        // Throwing here would break artisan itself.
        Cache::forget(FeatureFlags::CACHE_KEY);
        Schema::drop('system_features');

        $this->assertFalse((new FeatureFlags)->enabled('board-check'));
        $this->assertSame([], (new FeatureFlags)->all());
    }

    public function test_flags_are_independent_of_the_storage_disk(): void
    {
        // The old reader required an encrypted file on the default disk; a flag
        // enabled now must not depend on one existing.
        $this->flags()->enable('directory-search');

        $this->assertTrue(Helpers::isSystemFeatureEnabled('directory-search'));
        $this->assertFalse(
            Storage::fileExists('feature-flags/directory-search.flag')
        );
    }
}
