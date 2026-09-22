<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\ScanFaxSpoolLane;
use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Services\Faxing\FaxLaneRegistry;
use App\Services\Faxing\FaxSourceHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

class FaxLaneDispatchTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'ringcentral_client_id' => 'id',
            'ringcentral_client_secret' => encrypt('secret'),
            'ringcentral_jwt_token' => encrypt('jwt'),
            'ringcentral_api_endpoint' => 'https://platform.ringcentral.com',
        ]);

        $this->enableSystemFeature('cloud-faxing');
    }

    public function test_each_enabled_source_is_one_lane(): void
    {
        $this->assertSame(['mfax', 'ringcentral'], $this->laneKeys());
    }

    public function test_an_unpinned_source_is_still_a_single_lane(): void
    {
        FaxSpoolSource::create(['key' => 'is2', 'name' => 'IS 2']);

        // A source has one tosend/ folder. Producing a lane per provider would scan it
        // twice and submit every fax twice; the provider is chosen per fax instead.
        $this->assertSame(['is2'], $this->laneKeys(['is2']));
    }

    public function test_a_disabled_source_produces_no_lanes(): void
    {
        FaxSpoolSource::query()->where('key', 'mfax')->update(['enabled' => false]);

        $this->assertSame(['ringcentral'], $this->laneKeys());
    }

    public function test_a_source_pinned_to_an_unconfigured_provider_is_skipped(): void
    {
        DataSource::first()->update(['ringcentral_client_id' => null]);

        // Nothing can come out of the legacy ringcentral/ directory while RingCentral has
        // no credentials, so there is no point reading it.
        $this->assertSame(['mfax'], $this->laneKeys());
    }

    /**
     * @param  array<int, string>  $sourceKeys
     * @return array<int, string>
     */
    private function laneKeys(array $sourceKeys = []): array
    {
        return array_map(
            fn (FaxSpoolSource $source): string => $source->key,
            app(FaxLaneRegistry::class)->enabled($sourceKeys)
        );
    }

    public function test_the_dispatcher_queues_one_job_per_lane(): void
    {
        Bus::fake();

        $this->artisan('isfax:scan')->assertSuccessful();

        Bus::assertDispatchedTimes(ScanFaxSpoolLane::class, 2);
    }

    public function test_the_dispatcher_never_touches_the_filesystem(): void
    {
        Bus::fake();

        // Point a source at a directory that does not exist. If the dispatcher stat()ed
        // the spool it would be handing a dead mount's hang back to the scheduler, which
        // is the exact bug the queued lanes exist to avoid.
        FaxSpoolSource::create([
            'key' => 'is2',
            'name' => 'IS 2',
            'root_path' => '/nonexistent/definitely/not/here',
        ]);

        $this->artisan('isfax:scan --source=is2')->assertSuccessful();

        Bus::assertDispatchedTimes(ScanFaxSpoolLane::class, 1);
    }

    public function test_a_resting_source_is_skipped_but_can_be_forced(): void
    {
        Bus::fake();
        $health = app(FaxSourceHealth::class);

        $health->markFailed('mfax', 'unreachable');

        // A source that has just failed is rested rather than retried every minute...
        $this->artisan('isfax:scan --source=mfax')->assertSuccessful();
        Bus::assertNotDispatched(ScanFaxSpoolLane::class);

        // ...but resting is never a decision about traffic, only about timing, so an
        // operator who has fixed the mount can ask for it immediately.
        $this->artisan('isfax:scan --source=mfax --force')->assertSuccessful();
        Bus::assertDispatchedTimes(ScanFaxSpoolLane::class, 1);
    }

    public function test_it_does_nothing_when_cloud_faxing_is_switched_off(): void
    {
        Bus::fake();

        $this->disableSystemFeature('cloud-faxing');

        $this->artisan('isfax:scan')->assertSuccessful();

        Bus::assertNothingDispatched();
    }

    public function test_the_lane_job_is_unique_per_lane(): void
    {
        $this->assertSame('mfax', (new ScanFaxSpoolLane('mfax'))->uniqueId());
        $this->assertSame('is2', (new ScanFaxSpoolLane('is2'))->uniqueId());

        // Two sources must never share a scan lock, or one unreachable server would stop
        // a healthy one being scanned at all.
        $this->assertNotSame(
            (new ScanFaxSpoolLane('mfax'))->uniqueId(),
            (new ScanFaxSpoolLane('is2'))->uniqueId(),
        );
    }
}
