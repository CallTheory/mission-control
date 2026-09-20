<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * Which feature flag gates the MCP endpoint.
 *
 * Every piece of MCP interface -- System > MCP Server, the team utility, the
 * protocol test page -- is gated on 'mcp-server'. The endpoint itself was gated
 * on 'api-gateway', so an install with MCP on and the gateway off served the
 * entire UI in front of a route that did not exist, and the 404 explained
 * nothing. These pin the two to the same switch.
 *
 * Routes are registered at boot, before a test can flip a flag, so each case
 * re-loads routes/api.php against a fresh router with the flag already set.
 */
class McpRouteFeatureFlagTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Flags live in the system_features table (see InteractsWithFeatureFlags).
    }

    private function mcpRouteIsRegistered(): bool
    {
        // Re-run the routes file against the current flag state. Checked by URI,
        // not by name: the name lookup table is built at boot and does not pick up
        // routes registered afterwards.
        Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() === 'api/mcp/protocol') {
                return true;
            }
        }

        return false;
    }

    public function test_the_endpoint_is_served_when_mcp_server_is_enabled(): void
    {
        $this->enableSystemFeature('mcp-server');
        $this->disableSystemFeature('api-gateway');

        $this->assertTrue(
            $this->mcpRouteIsRegistered(),
            'MCP is enabled, so /api/mcp/protocol must exist even with the API gateway off.'
        );
    }

    public function test_the_endpoint_is_absent_when_mcp_server_is_disabled(): void
    {
        $this->disableSystemFeature('mcp-server');
        $this->enableSystemFeature('api-gateway');

        $this->assertFalse(
            $this->mcpRouteIsRegistered(),
            'MCP is disabled, so the API gateway being on must not expose /api/mcp/protocol.'
        );
    }
}
