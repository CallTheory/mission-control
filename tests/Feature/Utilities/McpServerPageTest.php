<?php

declare(strict_types=1);

namespace Tests\Feature\Utilities;

use App\Livewire\Utilities\McpServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The MCP tester talks Streamable HTTP.
 *
 * It used to open an EventSource against /api/mcp/user-info -- an endpoint that
 * never existed -- and passed the bearer token as a `headers` option, which
 * EventSource ignores outright. Nothing failed loudly, so the page sat there
 * doing nothing. The server has always spoken Streamable HTTP (one endpoint,
 * JSON-RPC over POST, GET answered 405), which is what MCP 2025-03-26 replaced
 * the HTTP+SSE transport with. These pin the page to it.
 */
class McpServerPageTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private function enabledUser(): User
    {
        $this->enableSystemFeature('mcp-server');

        $team = $this->createSeededTeam();
        $team->forceFill(['utility_mcp_server' => true])->save();

        return $this->createUserWithRole($team, 'admin');
    }

    /**
     * The page mounts this component with lazy="lazy", so an HTTP GET returns
     * only the placeholder -- the markup has to be asserted on the component.
     */
    private function renderedTester(): Testable
    {
        return Livewire::actingAs($this->enabledUser())->test(McpServer::class);
    }

    public function test_the_page_renders_for_an_enabled_team(): void
    {
        $this->actingAs($this->enabledUser())
            ->get('/utilities/mcp-server')
            ->assertOk();

        $this->renderedTester()->assertOk()->assertSee('Streamable HTTP');
    }

    public function test_it_posts_to_the_streamable_http_endpoint(): void
    {
        $this->renderedTester()
            ->assertSee('/api/mcp/protocol')
            ->assertSee('jsonrpc', false);
    }

    public function test_it_no_longer_uses_the_dead_sse_endpoint(): void
    {
        // /api/mcp/user-info is registered nowhere; EventSource silently drops a
        // headers option. Either one reappearing means the regression is back.
        $this->renderedTester()
            ->assertDontSee('/api/mcp/user-info')
            ->assertDontSee('EventSource');
    }

    public function test_it_announces_the_protocol_version_the_server_serves(): void
    {
        // App\Services\Mcp\McpServer replies with 2025-03-26; the old tester sent
        // 2024-11-05 on initialize, which disagreed with its own server.
        $this->renderedTester()
            ->assertSee('2025-03-26')
            ->assertDontSee('2024-11-05');
    }

    public function test_it_points_at_where_tokens_are_actually_created(): void
    {
        // The old copy said "your profile settings"; tokens live at /user/api-tokens.
        $this->renderedTester()
            ->assertSee(route('api-tokens.index'))
            ->assertDontSee('profile settings');
    }

    public function test_the_old_protocol_test_url_redirects_to_the_utility_page(): void
    {
        // It used to render the Livewire view directly: no app layout, and it
        // skipped the capability check McpServerController enforces.
        $this->actingAs($this->enabledUser())
            ->get('/utilities/mcp-protocol-test')
            ->assertRedirect(route('utilities.mcp-server'));
    }

    public function test_a_team_without_the_utility_cannot_reach_the_page(): void
    {
        $this->enableSystemFeature('mcp-server');

        $team = $this->createSeededTeam();
        $team->forceFill(['utility_mcp_server' => false])->save();
        $user = $this->createUserWithRole($team, 'admin');

        $this->actingAs($user)->get('/utilities/mcp-server')->assertNotFound();
    }

    public function test_the_deleted_duplicate_view_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/livewire/utilities/mcp-protocol-test.blade.php'));
        $this->assertFileDoesNotExist(app_path('Livewire/Utilities/McpProtocolTest.php'));
    }
}
