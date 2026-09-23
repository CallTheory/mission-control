<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Models\DataSource;
use App\Models\FaxSpoolSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The fax server switcher on the Cloud Faxing utility page.
 *
 * It is a view filter, not a setting — there is deliberately no "primary" server to
 * pick. Which Intelligent Series fax service is active is IS's decision; Mission
 * Control reads every server and whichever is producing files is the live one.
 *
 * It first shipped showing *every* enabled source, which on a stock install is the two
 * seeded provider-named ones — so it drew a second "mFax | RingCentral" row directly
 * under the provider tabs that already say that, and read like a setting.
 */
class CloudFaxingSourceSwitcherTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'mfax_api_key' => encrypt('key'),
            'mfax_enabled' => true,
            'ringcentral_client_id' => 'id',
            'ringcentral_enabled' => true,
        ]);

        $this->enableSystemFeature('cloud-faxing');

        $this->user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false, 'utility_cloud_faxing' => true]);
        $this->user->teams()->attach($team, ['role' => 'admin']);
        $this->user->switchTeam($team);
        $this->user = $this->user->fresh();
    }

    /**
     * @return Collection<int, FaxSpoolSource>
     */
    private function sourcesShownFor(string $path)
    {
        return $this->actingAs($this->user)->get($path)->viewData('sources');
    }

    public function test_a_stock_install_shows_no_server_switcher(): void
    {
        // Only the seeded mfax source can feed the mFax page, so there is nothing to
        // switch between and the partial renders nothing.
        $this->assertSame(['mfax'], $this->sourcesShownFor('/utilities/cloud-faxing')->pluck('key')->all());
        $this->assertSame(['ringcentral'], $this->sourcesShownFor('/utilities/cloud-faxing/ringcentral')->pluck('key')->all());
    }

    public function test_the_other_providers_server_is_never_offered(): void
    {
        // The regression: passing every enabled source duplicated the provider tabs.
        $this->assertNotContains('ringcentral', $this->sourcesShownFor('/utilities/cloud-faxing')->pluck('key')->all());
    }

    public function test_an_unpinned_server_appears_under_both_providers(): void
    {
        // A flat, provider-agnostic server feeds whichever provider routing picks.
        FaxSpoolSource::create(['key' => 'is2', 'name' => 'IS Building B']);

        $this->assertSame(['is2', 'mfax'], $this->sourcesShownFor('/utilities/cloud-faxing')->pluck('key')->sort()->values()->all());
        $this->assertSame(['is2', 'ringcentral'], $this->sourcesShownFor('/utilities/cloud-faxing/ringcentral')->pluck('key')->sort()->values()->all());
    }

    public function test_a_disabled_server_is_not_offered(): void
    {
        FaxSpoolSource::create(['key' => 'is2', 'name' => 'IS Building B', 'enabled' => false]);

        $this->assertSame(['mfax'], $this->sourcesShownFor('/utilities/cloud-faxing')->pluck('key')->all());
    }

    public function test_switching_server_changes_only_what_is_viewed(): void
    {
        FaxSpoolSource::create(['key' => 'is2', 'name' => 'IS Building B']);

        $before = FaxSpoolSource::query()->orderBy('key')->get()->toArray();

        $response = $this->actingAs($this->user)->get('/utilities/cloud-faxing?source=is2');

        $this->assertSame('is2', $response->viewData('sourceKey'));
        // Nothing is persisted: there is no primary server to set.
        $this->assertEquals($before, FaxSpoolSource::query()->orderBy('key')->get()->toArray());
    }

    public function test_an_unknown_server_falls_back_instead_of_erroring(): void
    {
        // These keys arrive from bookmarks and from alert emails that outlive the server
        // they named.
        $response = $this->actingAs($this->user)->get('/utilities/cloud-faxing?source=nope');

        $response->assertSuccessful();
        $this->assertSame('mfax', $response->viewData('sourceKey'));
    }
}
