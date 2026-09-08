<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Jobs\ProcessWctpMessage;
use App\Livewire\Utilities\WctpMessageViewer;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\User;
use App\Models\WctpMessage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

class WctpMessageViewerTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private User $user;

    private Team $team;

    private EnterpriseHost $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['session']->start();
        $this->enableWctpFeature();

        $this->team = $this->makeWctpTeam();
        $this->user = User::factory()->create();
        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->actingAs($this->user);

        // A default host owned by the acting team; messages hang off this by default.
        $this->host = EnterpriseHost::factory()->create(['team_id' => $this->team->id]);
    }

    protected function tearDown(): void
    {

        parent::tearDown();
    }

    private function enableWctpFeature(): void
    {
        $this->enableSystemFeature('wctp-gateway');
    }

    private function makeWctpTeam(): Team
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $team->forceFill(['utility_wctp_gateway' => true])->save();

        return $team->refresh();
    }

    /** Create a message owned by the acting team's default host unless a host is supplied. */
    private function msg(array $attributes = []): WctpMessage
    {
        return WctpMessage::factory()->create(array_merge(
            ['enterprise_host_id' => $this->host->id],
            $attributes
        ));
    }

    private function otherTeamHost(): EnterpriseHost
    {
        $otherTeam = Team::factory()->create(['personal_team' => false]);

        return EnterpriseHost::factory()->create(['team_id' => $otherTeam->id]);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_component_mounts_for_authorized_team(): void
    {
        Livewire::test(WctpMessageViewer::class)->assertSuccessful();
    }

    public function test_personal_team_is_forbidden(): void
    {
        $personal = Team::factory()->create(['personal_team' => true]);
        $this->user->teams()->attach($personal, ['role' => 'admin']);
        // Reload so the freshly attached team is visible to belongsToTeam()/switchTeam().
        $this->user->refresh();
        $this->user->switchTeam($personal);

        $this->get(route('utilities.wctp-messages'))->assertForbidden();
    }

    public function test_team_without_utility_flag_is_forbidden(): void
    {
        $this->team->forceFill(['utility_wctp_gateway' => false])->save();

        $this->get(route('utilities.wctp-messages'))->assertForbidden();
    }

    public function test_disabled_system_feature_is_forbidden(): void
    {
        $this->disableSystemFeature('wctp-gateway');

        $this->get(route('utilities.wctp-messages'))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Tenant isolation
    // ---------------------------------------------------------------------

    public function test_only_current_team_messages_are_visible(): void
    {
        $mine = $this->msg(['message' => 'My team message', 'wctp_message_id' => 'mine123']);
        $theirs = WctpMessage::factory()->create([
            'enterprise_host_id' => $this->otherTeamHost()->id,
            'message' => 'Their team message',
            'wctp_message_id' => 'theirs456',
        ]);

        Livewire::test(WctpMessageViewer::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_cannot_view_another_teams_message(): void
    {
        $theirs = WctpMessage::factory()->create(['enterprise_host_id' => $this->otherTeamHost()->id]);

        // The detail dialog is a table record action now, so the guard is the table's
        // own team scoping: a message outside it is not a row, and an action cannot be
        // mounted against a record the table will not resolve.
        $component = Livewire::test(WctpMessageViewer::class)
            ->assertCanNotSeeTableRecords([$theirs]);

        $component->mountTableAction('view', $theirs);

        $component->assertDontSee($theirs->wctp_message_id);
    }

    public function test_cannot_retry_another_teams_message(): void
    {
        Queue::fake();
        $theirs = WctpMessage::factory()->failed()->create([
            'enterprise_host_id' => $this->otherTeamHost()->id,
            'status' => 'failed',
        ]);

        Livewire::test(WctpMessageViewer::class)
            ->call('retryMessage', $theirs)
            ->assertForbidden();

        $this->assertSame('failed', $theirs->refresh()->status);
        Queue::assertNotPushed(ProcessWctpMessage::class);
    }

    public function test_hosts_filter_is_scoped_to_current_team(): void
    {
        $theirHost = $this->otherTeamHost();

        $options = Livewire::test(WctpMessageViewer::class)
            ->instance()
            ->getTable()
            ->getFilter('enterprise_host_id')
            ->getOptions();

        $this->assertArrayHasKey($this->host->id, $options);
        $this->assertArrayNotHasKey($theirHost->id, $options);
    }

    // ---------------------------------------------------------------------
    // Behaviour (scoped to the acting team)
    // ---------------------------------------------------------------------

    public function test_messages_ordered_by_created_at_desc(): void
    {
        $older = $this->msg(['created_at' => now()->subHour()]);
        $newer = $this->msg(['created_at' => now()]);

        Livewire::test(WctpMessageViewer::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_host_filter_from_query_string(): void
    {
        $host2 = EnterpriseHost::factory()->create(['team_id' => $this->team->id]);

        $onHost1 = $this->msg(['message' => 'Host 1 message', 'wctp_message_id' => 'host1msg']);
        $onHost2 = $this->msg(['enterprise_host_id' => $host2->id, 'message' => 'Host 2 message', 'wctp_message_id' => 'host2msg']);

        Livewire::test(WctpMessageViewer::class)
            ->set('host', $this->host->id)
            ->assertCanSeeTableRecords([$onHost1])
            ->assertCanNotSeeTableRecords([$onHost2]);
    }

    public function test_search_functionality(): void
    {
        $message1 = $this->msg([
            'to' => '5551234567',
            'from' => '+15552345678',
            'wctp_message_id' => 'msg123unique',
        ]);
        $message2 = $this->msg([
            'to' => '5559876543',
            'from' => '+15553456789',
            'wctp_message_id' => 'msg456different',
        ]);

        Livewire::test(WctpMessageViewer::class)
            ->searchTable('5551234567')
            ->assertCanSeeTableRecords([$message1])
            ->assertCanNotSeeTableRecords([$message2])
            ->searchTable('msg123unique')
            ->assertCanSeeTableRecords([$message1])
            ->assertCanNotSeeTableRecords([$message2]);
    }

    public function test_status_filter(): void
    {
        $pending = $this->msg(['status' => 'pending', 'wctp_message_id' => 'pendingmsg']);
        $delivered = $this->msg(['status' => 'delivered', 'wctp_message_id' => 'deliveredmsg']);

        Livewire::test(WctpMessageViewer::class)
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$delivered])
            ->filterTable('status', 'delivered')
            ->assertCanSeeTableRecords([$delivered])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_direction_filter(): void
    {
        $outbound = $this->msg(['direction' => 'outbound', 'wctp_message_id' => 'outboundmsg']);
        $inbound = $this->msg(['direction' => 'inbound', 'wctp_message_id' => 'inboundmsg']);

        Livewire::test(WctpMessageViewer::class)
            ->filterTable('direction', 'outbound')
            ->assertCanSeeTableRecords([$outbound])
            ->assertCanNotSeeTableRecords([$inbound])
            ->filterTable('direction', 'inbound')
            ->assertCanSeeTableRecords([$inbound])
            ->assertCanNotSeeTableRecords([$outbound]);
    }

    public function test_date_range_filter(): void
    {
        $before = $this->msg(['created_at' => '2023-01-01 12:00:00', 'wctp_message_id' => 'beforerange']);
        $within = $this->msg(['created_at' => '2023-03-15 12:00:00', 'wctp_message_id' => 'withinrange']);
        $after = $this->msg(['created_at' => '2023-06-01 12:00:00', 'wctp_message_id' => 'afterrange']);

        Livewire::test(WctpMessageViewer::class)
            ->filterTable('created_at', ['dateFrom' => '2023-03-01', 'dateTo' => '2023-04-01'])
            ->assertCanSeeTableRecords([$within])
            ->assertCanNotSeeTableRecords([$before, $after]);
    }

    public function test_view_message_modal_shows_the_detail(): void
    {
        $message = $this->msg(['wctp_message_id' => 'test123']);

        // Filament loads modal bodies lazily through a separate partial, so the
        // assertion is that the action mounted against this record -- the schema it
        // renders is declarative and reads straight off it.
        Livewire::test(WctpMessageViewer::class)
            ->mountTableAction('view', $message)
            ->assertActionMounted(TestAction::make('view')->table($message));
    }

    public function test_retry_failed_message(): void
    {
        Queue::fake();

        $message = $this->msg(['status' => 'failed', 'failed_at' => now()]);

        Livewire::test(WctpMessageViewer::class)->call('retryMessage', $message);

        $message->refresh();
        $this->assertEquals('pending', $message->status);
        $this->assertNull($message->failed_at);

        Queue::assertPushed(ProcessWctpMessage::class);
    }

    public function test_retry_non_retryable_message_does_nothing(): void
    {
        Queue::fake();

        $message = $this->msg(['status' => 'delivered']);

        Livewire::test(WctpMessageViewer::class)->call('retryMessage', $message);

        $this->assertEquals('delivered', $message->refresh()->status);
        Queue::assertNotPushed(ProcessWctpMessage::class);
    }

    public function test_pagination_resets_on_search_change(): void
    {
        WctpMessage::factory()->count(25)->create(['enterprise_host_id' => $this->host->id]);

        // Filament owns pagination now; searching from a later page must land back on
        // page 1 rather than an out-of-range page that would render empty.
        Livewire::test(WctpMessageViewer::class)
            ->set('tableRecordsPerPage', 20)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->searchTable('555')
            ->assertSet('paginators.page', 1);
    }

    public function test_query_string_properties(): void
    {
        $component = new WctpMessageViewer;

        // Search, status and direction now live in Filament's own query string; only
        // the host pin is still owned by the component.
        $expectedQueryString = [
            'host' => ['except' => null],
        ];

        $reflection = new \ReflectionClass($component);
        $property = $reflection->getProperty('queryString');
        $property->setAccessible(true);

        $this->assertEquals($expectedQueryString, $property->getValue($component));
    }

    public function test_messages_loaded_with_enterprise_host_relationship(): void
    {
        $this->host->forceFill(['name' => 'Test Host'])->save();
        $this->msg();

        // The Host column reads through the relationship, so seeing the host name
        // rendered proves it was loaded.
        Livewire::test(WctpMessageViewer::class)
            ->assertCanRenderTableColumn('enterpriseHost.name')
            ->assertSee('Test Host');
    }
}
