<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Jobs\ProcessWctpMessage;
use App\Livewire\System\Wctp\Messages;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\WctpMessage;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

/**
 * The WCTP message log.
 *
 * Reading it needs wctp.messages; retrying a message needs wctp.manage, because a
 * retry puts traffic back on the queue rather than just reading it. The log is not
 * team-scoped — the gateway is one installation-wide configuration.
 */
class MessagesTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private Team $team;

    private EnterpriseHost $host;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableSystemFeature('wctp-gateway');
        $this->team = $this->createSeededTeam();
        $this->host = EnterpriseHost::factory()->create();

        $this->actingAs($this->createUserWithRole($this->team, 'admin'));
    }

    private function msg(array $attributes = []): WctpMessage
    {
        return WctpMessage::factory()->create([
            'enterprise_host_id' => $this->host->id,
            ...$attributes,
        ]);
    }

    // ---------------------------------------------------------------------
    // Authorization
    // ---------------------------------------------------------------------

    public function test_it_mounts_for_a_user_with_the_message_capability(): void
    {
        $this->actingAs($this->createUserWithout($this->team, 'technical', Capability::WctpManage));

        Livewire::test(Messages::class)->assertSuccessful();
    }

    public function test_a_user_without_the_message_capability_is_denied(): void
    {
        $this->actingAs($this->createUserWithRole($this->team, 'agent'));

        Livewire::test(Messages::class)->assertForbidden();
    }

    public function test_retrying_needs_the_manage_capability_the_log_itself_does_not(): void
    {
        // The guard that matters: reading the log is not permission to put traffic
        // back on the queue, and Livewire reaches an action without re-running the
        // controller's check.
        Queue::fake();

        $message = $this->msg(['status' => 'failed', 'failed_at' => now()]);
        $this->actingAs($this->createUserWithout($this->team, 'technical', Capability::WctpManage));

        Livewire::test(Messages::class)
            ->call('retryMessage', $message)
            ->assertForbidden();

        $this->assertSame('failed', $message->refresh()->status);
        Queue::assertNotPushed(ProcessWctpMessage::class);
    }

    public function test_it_is_absent_when_the_system_feature_is_off(): void
    {
        $this->disableSystemFeature('wctp-gateway');

        Livewire::test(Messages::class)->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Listing
    // ---------------------------------------------------------------------

    public function test_it_lists_messages_from_every_host_regardless_of_team(): void
    {
        $mine = $this->msg(['wctp_message_id' => 'mine123']);
        $anothers = WctpMessage::factory()->create([
            'enterprise_host_id' => EnterpriseHost::factory()->create([
                'team_id' => Team::factory()->create()->id,
            ])->id,
            'wctp_message_id' => 'theirs456',
        ]);

        Livewire::test(Messages::class)->assertCanSeeTableRecords([$mine, $anothers]);
    }

    public function test_the_host_filter_offers_every_host(): void
    {
        $other = EnterpriseHost::factory()->create(['team_id' => Team::factory()->create()->id]);

        $options = Livewire::test(Messages::class)
            ->instance()
            ->getTable()
            ->getFilter('enterprise_host_id')
            ->getOptions();

        $this->assertArrayHasKey($this->host->id, $options);
        $this->assertArrayHasKey($other->id, $options);
    }

    public function test_messages_are_ordered_newest_first(): void
    {
        $older = $this->msg(['created_at' => now()->subHour()]);
        $newer = $this->msg(['created_at' => now()]);

        Livewire::test(Messages::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }

    public function test_the_log_can_be_pinned_to_one_host(): void
    {
        $host2 = EnterpriseHost::factory()->create();

        $onHost1 = $this->msg(['wctp_message_id' => 'host1msg']);
        $onHost2 = $this->msg(['enterprise_host_id' => $host2->id, 'wctp_message_id' => 'host2msg']);

        Livewire::test(Messages::class)
            ->set('host', $this->host->id)
            ->assertCanSeeTableRecords([$onHost1])
            ->assertCanNotSeeTableRecords([$onHost2]);
    }

    public function test_search_matches_numbers_and_message_ids(): void
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

        Livewire::test(Messages::class)
            ->searchTable('5551234567')
            ->assertCanSeeTableRecords([$message1])
            ->assertCanNotSeeTableRecords([$message2])
            ->searchTable('msg123unique')
            ->assertCanSeeTableRecords([$message1])
            ->assertCanNotSeeTableRecords([$message2]);
    }

    public function test_the_status_filter_works(): void
    {
        $pending = $this->msg(['status' => 'pending', 'wctp_message_id' => 'pendingmsg']);
        $delivered = $this->msg(['status' => 'delivered', 'wctp_message_id' => 'deliveredmsg']);

        Livewire::test(Messages::class)
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$delivered])
            ->filterTable('status', 'delivered')
            ->assertCanSeeTableRecords([$delivered])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_the_direction_filter_works(): void
    {
        $outbound = $this->msg(['direction' => 'outbound', 'wctp_message_id' => 'outboundmsg']);
        $inbound = $this->msg(['direction' => 'inbound', 'wctp_message_id' => 'inboundmsg']);

        Livewire::test(Messages::class)
            ->filterTable('direction', 'outbound')
            ->assertCanSeeTableRecords([$outbound])
            ->assertCanNotSeeTableRecords([$inbound])
            ->filterTable('direction', 'inbound')
            ->assertCanSeeTableRecords([$inbound])
            ->assertCanNotSeeTableRecords([$outbound]);
    }

    public function test_the_date_range_filter_works(): void
    {
        $before = $this->msg(['created_at' => '2023-01-01 12:00:00', 'wctp_message_id' => 'beforerange']);
        $within = $this->msg(['created_at' => '2023-03-15 12:00:00', 'wctp_message_id' => 'withinrange']);
        $after = $this->msg(['created_at' => '2023-06-01 12:00:00', 'wctp_message_id' => 'afterrange']);

        Livewire::test(Messages::class)
            ->filterTable('created_at', ['dateFrom' => '2023-03-01', 'dateTo' => '2023-04-01'])
            ->assertCanSeeTableRecords([$within])
            ->assertCanNotSeeTableRecords([$before, $after]);
    }

    public function test_the_carrier_column_names_the_carrier_that_carried_the_message(): void
    {
        $this->msg(['provider' => 'bandwidth', 'wctp_message_id' => 'viabandwidth']);

        Livewire::test(Messages::class)
            ->assertCanRenderTableColumn('provider')
            ->assertSee('Bandwidth');
    }

    // ---------------------------------------------------------------------
    // Actions
    // ---------------------------------------------------------------------

    public function test_the_detail_dialog_mounts_against_the_record(): void
    {
        $message = $this->msg(['wctp_message_id' => 'test123']);

        // Filament loads modal bodies lazily through a separate partial, so the
        // assertion is that the action mounted against this record -- the schema it
        // renders is declarative and reads straight off it.
        Livewire::test(Messages::class)
            ->mountTableAction('view', $message)
            ->assertActionMounted(TestAction::make('view')->table($message));
    }

    public function test_retrying_a_failed_message_requeues_it(): void
    {
        Queue::fake();

        $message = $this->msg(['status' => 'failed', 'failed_at' => now()]);

        Livewire::test(Messages::class)->call('retryMessage', $message);

        $message->refresh();

        $this->assertSame('pending', $message->status);
        $this->assertNull($message->failed_at);

        Queue::assertPushed(ProcessWctpMessage::class);
    }

    public function test_retrying_a_delivered_message_does_nothing(): void
    {
        Queue::fake();

        $message = $this->msg(['status' => 'delivered']);

        Livewire::test(Messages::class)->call('retryMessage', $message);

        $this->assertSame('delivered', $message->refresh()->status);
        Queue::assertNotPushed(ProcessWctpMessage::class);
    }

    public function test_searching_from_a_later_page_lands_back_on_the_first(): void
    {
        WctpMessage::factory()->count(25)->create(['enterprise_host_id' => $this->host->id]);

        Livewire::test(Messages::class)
            ->set('tableRecordsPerPage', 20)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->searchTable('555')
            ->assertSet('paginators.page', 1);
    }

    public function test_only_the_host_pin_lives_in_the_query_string(): void
    {
        // Search, status and direction live in Filament's own query string.
        $reflection = new \ReflectionClass(new Messages);
        $property = $reflection->getProperty('queryString');
        $property->setAccessible(true);

        $this->assertSame(['host' => ['except' => null]], $property->getValue(new Messages));
    }

    public function test_the_host_column_reads_through_the_relationship(): void
    {
        $this->host->forceFill(['name' => 'Test Host'])->save();
        $this->msg();

        Livewire::test(Messages::class)
            ->assertCanRenderTableColumn('enterpriseHost.name')
            ->assertSee('Test Host');
    }
}
