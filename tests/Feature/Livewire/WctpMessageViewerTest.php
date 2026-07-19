<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Jobs\ProcessWctpMessage;
use App\Livewire\Utilities\WctpMessageViewer;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\User;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class WctpMessageViewerTest extends TestCase
{
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
        Storage::delete('feature-flags/wctp-gateway.flag');

        parent::tearDown();
    }

    private function enableWctpFeature(): void
    {
        Storage::makeDirectory('feature-flags');
        Storage::put('feature-flags/wctp-gateway.flag', encrypt('wctp-gateway'));
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
        Storage::delete('feature-flags/wctp-gateway.flag');

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
            ->assertViewHas('messages', function ($messages) use ($mine, $theirs) {
                $ids = $messages->pluck('id')->all();

                return in_array($mine->id, $ids, true)
                    && ! in_array($theirs->id, $ids, true);
            })
            ->assertSee('mine123')
            ->assertDontSee('theirs456');
    }

    public function test_cannot_view_another_teams_message(): void
    {
        $theirs = WctpMessage::factory()->create(['enterprise_host_id' => $this->otherTeamHost()->id]);

        Livewire::test(WctpMessageViewer::class)
            ->call('viewMessage', $theirs)
            ->assertForbidden();
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
        $this->otherTeamHost();

        Livewire::test(WctpMessageViewer::class)
            ->assertViewHas('hosts', function ($hosts) {
                return $hosts->every(fn ($host) => (int) $host->team_id === $this->team->id);
            });
    }

    // ---------------------------------------------------------------------
    // Behaviour (scoped to the acting team)
    // ---------------------------------------------------------------------

    public function test_messages_ordered_by_created_at_desc(): void
    {
        $older = $this->msg(['created_at' => now()->subHour()]);
        $newer = $this->msg(['created_at' => now()]);

        Livewire::test(WctpMessageViewer::class)
            ->assertViewHas('messages', function ($messages) use ($newer, $older) {
                return $messages->first()->id === $newer->id && $messages->last()->id === $older->id;
            });
    }

    public function test_host_filter_from_query_string(): void
    {
        $host2 = EnterpriseHost::factory()->create(['team_id' => $this->team->id]);

        $this->msg(['message' => 'Host 1 message', 'wctp_message_id' => 'host1msg']);
        $this->msg(['enterprise_host_id' => $host2->id, 'message' => 'Host 2 message', 'wctp_message_id' => 'host2msg']);

        Livewire::test(WctpMessageViewer::class)
            ->set('host', $this->host->id)
            ->assertSee('host1msg')
            ->assertDontSee('host2msg');
    }

    public function test_search_functionality(): void
    {
        $message1 = $this->msg([
            'to' => '5551234567',
            'from' => '+15552345678',
            'wctp_message_id' => 'msg123unique',
        ]);
        $this->msg([
            'to' => '5559876543',
            'from' => '+15553456789',
            'wctp_message_id' => 'msg456different',
        ]);

        $messages = Livewire::test(WctpMessageViewer::class)
            ->set('search', '5551234567')
            ->viewData('messages');
        $this->assertEquals(1, $messages->count());
        $this->assertEquals($message1->id, $messages->first()->id);

        $messages = Livewire::test(WctpMessageViewer::class)
            ->set('search', 'msg123unique')
            ->viewData('messages');
        $this->assertEquals(1, $messages->count());
        $this->assertEquals($message1->id, $messages->first()->id);
    }

    public function test_status_filter(): void
    {
        $this->msg(['status' => 'pending', 'wctp_message_id' => 'pendingmsg']);
        $this->msg(['status' => 'delivered', 'wctp_message_id' => 'deliveredmsg']);

        Livewire::test(WctpMessageViewer::class)
            ->set('filterStatus', 'pending')
            ->assertSee('pendingmsg')
            ->assertDontSee('deliveredmsg')
            ->set('filterStatus', 'delivered')
            ->assertSee('deliveredmsg')
            ->assertDontSee('pendingmsg');
    }

    public function test_direction_filter(): void
    {
        $this->msg(['direction' => 'outbound', 'wctp_message_id' => 'outboundmsg']);
        $this->msg(['direction' => 'inbound', 'wctp_message_id' => 'inboundmsg']);

        Livewire::test(WctpMessageViewer::class)
            ->set('filterDirection', 'outbound')
            ->assertSee('outboundmsg')
            ->assertDontSee('inboundmsg')
            ->set('filterDirection', 'inbound')
            ->assertSee('inboundmsg')
            ->assertDontSee('outboundmsg');
    }

    public function test_date_range_filter(): void
    {
        $this->msg(['created_at' => '2023-01-01 12:00:00', 'wctp_message_id' => 'beforerange']);
        $this->msg(['created_at' => '2023-03-15 12:00:00', 'wctp_message_id' => 'withinrange']);
        $this->msg(['created_at' => '2023-06-01 12:00:00', 'wctp_message_id' => 'afterrange']);

        Livewire::test(WctpMessageViewer::class)
            ->set('dateFrom', '2023-03-01')
            ->set('dateTo', '2023-04-01')
            ->assertSee('withinrange')
            ->assertDontSee('beforerange')
            ->assertDontSee('afterrange');
    }

    public function test_view_message_modal(): void
    {
        $message = $this->msg(['wctp_message_id' => 'test123']);

        Livewire::test(WctpMessageViewer::class)
            ->call('viewMessage', $message)
            ->assertSet('selectedMessage', $message);
    }

    public function test_close_message_modal(): void
    {
        $message = $this->msg();

        Livewire::test(WctpMessageViewer::class)
            ->set('selectedMessage', $message)
            ->call('closeMessageModal')
            ->assertSet('selectedMessage', null);
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

        $component = Livewire::test(WctpMessageViewer::class);
        $this->assertGreaterThan(1, $component->viewData('messages')->lastPage());

        $component->set('search', '555');
        $this->assertEquals(1, $component->viewData('messages')->currentPage());
    }

    public function test_query_string_properties(): void
    {
        $component = new WctpMessageViewer();

        $expectedQueryString = [
            'search' => ['except' => ''],
            'filterStatus' => ['except' => ''],
            'filterDirection' => ['except' => ''],
            'filterCarrier' => ['except' => ''],
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

        Livewire::test(WctpMessageViewer::class)
            ->assertViewHas('messages', function ($messages) {
                $message = $messages->first();

                return $message->enterpriseHost !== null
                    && $message->enterpriseHost->name === 'Test Host';
            });
    }
}
