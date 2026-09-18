<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\SmsProvider;
use App\Http\Middleware\ValidateSmsProviderWebhook;
use App\Http\Middleware\ValidateTwilioRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The WCTP routes as routes/web.php actually defines them.
 *
 * Every other WCTP test registers the routes it needs by hand, because the real ones
 * sit behind a system feature flag read at route-registration time and so are absent
 * from a test's route table. That leaves the definitions themselves -- paths, names,
 * middleware, the provider constraint -- with no coverage at all, which is how a
 * variable the route-group closure never captured got as far as it did. This test
 * re-runs the routes file with the flag on and inspects what it produced.
 */
class WctpRouteRegistrationTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');

        // Routes were registered at boot with the flag off; run the file again now
        // that it is on. Re-registering the rest of the file is harmless here --
        // nothing in this test issues a request.
        require base_path('routes/web.php');

        Route::getRoutes()->refreshNameLookups();
    }

    private function route(string $name): RoutingRoute
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route [{$name}] was not registered.");

        return $route;
    }

    public function test_the_twilio_routes_keep_their_original_paths(): void
    {
        $this->assertSame('wctp/sms/incoming', $this->route('wctp.sms.incoming')->uri());
        $this->assertSame('wctp/callback/{messageId}', $this->route('wctp.callback')->uri());

        foreach (['wctp.sms.incoming', 'wctp.callback'] as $name) {
            $this->assertContains(
                ValidateTwilioRequest::class,
                $this->route($name)->gatherMiddleware(),
            );
        }
    }

    public function test_the_per_carrier_routes_are_registered_and_authenticated(): void
    {
        $inbound = $this->route('wctp.sms.provider.incoming');
        $callback = $this->route('wctp.provider.callback');

        $this->assertSame('wctp/sms/{provider}/incoming', $inbound->uri());
        // The message id is optional: Bandwidth and Com.io configure one URL and
        // identify the message inside the payload.
        $this->assertSame('wctp/{provider}/callback/{messageId?}', $callback->uri());

        foreach ([$inbound, $callback] as $route) {
            $this->assertContains(
                ValidateSmsProviderWebhook::class,
                $route->gatherMiddleware(),
            );
        }
    }

    public function test_the_provider_segment_only_matches_a_known_carrier(): void
    {
        foreach (['wctp.sms.provider.incoming', 'wctp.provider.callback'] as $name) {
            $pattern = $this->route($name)->wheres['provider'] ?? null;

            $this->assertNotNull($pattern, "Route [{$name}] does not constrain its provider segment.");

            foreach (SmsProvider::cases() as $provider) {
                $this->assertMatchesRegularExpression('#^'.$pattern.'$#', $provider->value);
            }

            $this->assertDoesNotMatchRegularExpression('#^'.$pattern.'$#', 'carrierpigeon');
        }
    }

    public function test_the_wctp_endpoint_is_throttled(): void
    {
        $this->assertContains('throttle:60,1', $this->route('wctp')->gatherMiddleware());
    }
}
