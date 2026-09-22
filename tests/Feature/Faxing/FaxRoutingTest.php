<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Enums\FaxProvider;
use App\Models\DataSource;
use App\Models\FaxProviderPin;
use App\Models\FaxSpoolSource;
use App\Services\Faxing\FaxRoute;
use App\Services\Faxing\FaxRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaxRoutingTest extends TestCase
{
    use RefreshDatabase;

    private FaxRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'ringcentral_client_id' => 'id',
            'ringcentral_client_secret' => encrypt('secret'),
            'ringcentral_jwt_token' => encrypt('jwt'),
            'ringcentral_api_endpoint' => 'https://platform.ringcentral.com',
        ]);

        $this->router = app(FaxRouter::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function fax(string $phone = '9139069098'): array
    {
        return ['phone' => $phone, 'jobID' => 4242, 'fsFileName' => 'IS20.fs'];
    }

    public function test_a_legacy_source_keeps_sending_through_its_own_provider(): void
    {
        // The whole backward-compatibility promise: a fax that arrived in the ringcentral/
        // directory still goes out through RingCentral, with nothing configured.
        $route = $this->router->route($this->fax(), FaxSpoolSource::findByKey('ringcentral'));

        $this->assertSame(FaxProvider::RingCentral, $route->provider);
        $this->assertSame('spool source', $route->reason);
    }

    public function test_an_unpinned_source_uses_the_system_default(): void
    {
        DataSource::first()->update(['fax_default_provider' => 'ringcentral']);
        $source = FaxSpoolSource::create(['key' => 'is2', 'name' => 'IS 2']);

        $route = $this->router->route($this->fax(), $source);

        // This is the headline: the provider changed with no Intelligent Series change.
        $this->assertSame(FaxProvider::RingCentral, $route->provider);
        $this->assertSame('system default', $route->reason);
    }

    public function test_a_number_pin_outranks_everything(): void
    {
        FaxProviderPin::create([
            'match_type' => FaxProviderPin::MATCH_NUMBER,
            'match_value' => '(913) 906-9098',
            'provider' => 'ringcentral',
            'note' => 'mFax route to this number keeps failing',
        ]);

        // Even for a fax that arrived in the legacy mfax/ directory: the pin is an
        // explicit operator override, made because that provider is broken for it.
        $route = $this->router->route($this->fax(), FaxSpoolSource::findByKey('mfax'));

        $this->assertSame(FaxProvider::RingCentral, $route->provider);
        $this->assertSame('pinned by number', $route->reason);
    }

    public function test_a_pin_matches_however_the_number_is_written(): void
    {
        FaxProviderPin::create([
            'match_type' => FaxProviderPin::MATCH_NUMBER,
            'match_value' => '9139069098',
            'provider' => 'ringcentral',
        ]);

        // .fs files carry a trailing semicolon; pins get typed with punctuation.
        foreach (['9139069098', '19139069098', '+1 (913) 906-9098', '913-906-9098;'] as $written) {
            $this->assertSame(
                FaxProvider::RingCentral,
                $this->router->route($this->fax($written), FaxSpoolSource::findByKey('mfax'))->provider,
                $written,
            );
        }
    }

    public function test_an_account_pin_applies_when_no_number_pin_matches(): void
    {
        FaxProviderPin::create([
            'match_type' => FaxProviderPin::MATCH_ACCOUNT,
            'match_value' => '12345',
            'provider' => 'ringcentral',
        ]);

        $route = $this->router->route(
            $this->fax(),
            FaxSpoolSource::findByKey('mfax'),
            fn (): string => '12345',
        );

        $this->assertSame(FaxProvider::RingCentral, $route->provider);
        $this->assertSame('pinned by account', $route->reason);
    }

    public function test_the_account_is_not_resolved_when_no_account_pins_exist(): void
    {
        $resolved = false;

        // Resolving the account is a query against the Intelligent Series database, and
        // paying for it per fax when nobody uses account pins would be pure waste.
        $this->router->route($this->fax(), FaxSpoolSource::findByKey('mfax'), function () use (&$resolved) {
            $resolved = true;

            return '12345';
        });

        $this->assertFalse($resolved);
    }

    public function test_a_number_pin_wins_over_an_account_pin(): void
    {
        FaxProviderPin::create(['match_type' => FaxProviderPin::MATCH_NUMBER, 'match_value' => '9139069098', 'provider' => 'mfax']);
        FaxProviderPin::create(['match_type' => FaxProviderPin::MATCH_ACCOUNT, 'match_value' => '12345', 'provider' => 'ringcentral']);

        $route = $this->router->route($this->fax(), null, fn (): string => '12345');

        $this->assertSame(FaxProvider::Mfax, $route->provider);
    }

    public function test_a_pin_to_an_unconfigured_provider_is_ignored(): void
    {
        DataSource::first()->update(['ringcentral_client_id' => null]);
        FaxProviderPin::create(['match_type' => FaxProviderPin::MATCH_NUMBER, 'match_value' => '9139069098', 'provider' => 'ringcentral']);

        // Better to send through the provider that works than to fail a fax over a pin
        // that can no longer be honoured.
        $route = $this->router->route($this->fax(), FaxSpoolSource::findByKey('mfax'));

        $this->assertSame(FaxProvider::Mfax, $route->provider);
    }

    public function test_a_disabled_pin_is_ignored(): void
    {
        FaxProviderPin::create([
            'match_type' => FaxProviderPin::MATCH_NUMBER,
            'match_value' => '9139069098',
            'provider' => 'ringcentral',
            'enabled' => false,
        ]);

        $this->assertSame(FaxProvider::Mfax, $this->router->route($this->fax(), FaxSpoolSource::findByKey('mfax'))->provider);
    }

    public function test_failover_is_off_unless_it_is_switched_on(): void
    {
        $route = new FaxRoute(FaxProvider::Mfax, 'system default', true);

        // The route permits it, but the system setting has not been enabled, so an
        // install that has only ever used one provider does not silently start using the
        // other because of a transient error.
        $this->assertNull($this->router->nextProvider($route, ['mfax']));
    }

    public function test_failover_moves_to_the_other_configured_provider(): void
    {
        DataSource::first()->update(['fax_failover_enabled' => true]);

        $route = new FaxRoute(FaxProvider::Mfax, 'system default', true);

        $this->assertSame(FaxProvider::RingCentral, $this->router->nextProvider($route, ['mfax']));
    }

    public function test_failover_stops_once_every_provider_has_been_tried(): void
    {
        DataSource::first()->update(['fax_failover_enabled' => true]);

        $route = new FaxRoute(FaxProvider::Mfax, 'system default', true);

        // Otherwise a fax would bounce between providers until its retry window expired.
        $this->assertNull($this->router->nextProvider($route, ['mfax', 'ringcentral']));
    }

    public function test_a_hard_pin_refuses_failover_even_when_it_is_enabled(): void
    {
        DataSource::first()->update(['fax_failover_enabled' => true]);

        // Pinned precisely because the other provider is broken for this fax; falling
        // back to it would undo the fix.
        $route = new FaxRoute(FaxProvider::RingCentral, 'pinned by number', false);

        $this->assertNull($this->router->nextProvider($route, ['ringcentral']));
    }

    public function test_a_soft_pin_allows_failover(): void
    {
        DataSource::first()->update(['fax_failover_enabled' => true]);

        FaxProviderPin::create([
            'match_type' => FaxProviderPin::MATCH_NUMBER,
            'match_value' => '9139069098',
            'provider' => 'ringcentral',
            'allow_failover' => true,
        ]);

        $route = $this->router->route($this->fax(), null);

        $this->assertTrue($route->allowFailover);
        $this->assertSame(FaxProvider::Mfax, $this->router->nextProvider($route, ['ringcentral']));
    }
}
