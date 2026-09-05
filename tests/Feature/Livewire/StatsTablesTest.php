<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Accounts\Clients as AccountsClients;
use App\Livewire\Analytics\Agents;
use App\Livewire\Analytics\CallLog;
use App\Livewire\Analytics\Clients as AnalyticsClients;
use App\Livewire\Utilities\BoardActivity;
use App\Models\Stats\BoardCheck\Activity;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Analytics screens read the Amtelco SQL Server through App\Models\Stats\*, which
 * no test environment has. What is worth pinning down is the behaviour that survives
 * that: an unreachable stats database must render an empty table, not a 500. The
 * search/sort/paging logic those screens depend on is covered directly in
 * Tests\Unit\Support\StatRecordsTest.
 */
class StatsTablesTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create();
    }

    /** A user on a real (non-personal) team, which the account-scoped screens require. */
    private function teamActor(): User
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user->fresh();
    }

    public function test_agents_renders_an_empty_table_when_the_stats_database_is_unreachable(): void
    {
        Livewire::actingAs($this->actor())
            ->test(Agents::class)
            ->assertOk()
            ->assertSee('No agent activity in the last 24 hours.');
    }

    public function test_analytics_clients_renders_an_empty_table_when_the_stats_database_is_unreachable(): void
    {
        Livewire::actingAs($this->actor())
            ->test(AnalyticsClients::class)
            ->assertOk()
            ->assertSee('No records found.');
    }

    public function test_accounts_clients_renders_an_empty_table_when_the_stats_database_is_unreachable(): void
    {
        Livewire::actingAs($this->teamActor())
            ->test(AccountsClients::class)
            ->assertOk()
            ->assertSee('No records found.');
    }

    public function test_call_log_renders_an_empty_table_when_the_stats_database_is_unreachable(): void
    {
        Settings::firstOrCreate([], ['switch_data_timezone' => 'UTC']);

        Livewire::actingAs($this->teamActor())
            ->test(CallLog::class)
            ->assertOk()
            ->assertSee('No calls match these filters.');
    }

    // ------------------------------------------------------------------
    // Board activity is Eloquent-backed despite living under App\Models\Stats,
    // so it can be exercised for real.
    // ------------------------------------------------------------------

    public function test_board_activity_lists_records_newest_first(): void
    {
        $user = $this->actor();

        $older = Activity::create([
            'user_id' => $user->id, 'msgId' => 1001,
            'activity_type' => 'reviewed', 'created_at' => now()->subHour(),
        ]);
        $newer = Activity::create([
            'user_id' => $user->id, 'msgId' => 1002,
            'activity_type' => 'approved', 'created_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(BoardActivity::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCanRenderTableColumn('user.name')
            ->assertCanRenderTableColumn('activity_type');
    }

    public function test_board_activity_resolves_the_user_through_the_relationship(): void
    {
        $user = $this->actor();
        $user->forceFill(['name' => 'Dana Whitfield'])->save();

        Activity::create([
            'user_id' => $user->id, 'msgId' => 1001, 'activity_type' => 'reviewed',
        ]);

        Livewire::actingAs($user)
            ->test(BoardActivity::class)
            ->assertSee('Dana Whitfield');
    }

    public function test_board_activity_falls_back_when_the_user_is_gone(): void
    {
        $actor = $this->actor();

        Activity::create([
            'user_id' => 999_999, 'msgId' => 1001, 'activity_type' => 'reviewed',
        ]);

        Livewire::actingAs($actor)
            ->test(BoardActivity::class)
            ->assertSee('Unknown User');
    }

    public function test_board_activity_can_be_pinned_to_a_message_id_on_mount(): void
    {
        $user = $this->actor();

        $wanted = Activity::create(['user_id' => $user->id, 'msgId' => 1001, 'activity_type' => 'reviewed']);
        $other = Activity::create(['user_id' => $user->id, 'msgId' => 2002, 'activity_type' => 'approved']);

        Livewire::actingAs($user)
            ->test(BoardActivity::class, ['msgId' => 1001])
            ->assertCanSeeTableRecords([$wanted])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_board_activity_filters_by_user(): void
    {
        $actor = $this->actor();
        $other = User::factory()->create();

        $mine = Activity::create(['user_id' => $actor->id, 'msgId' => 1001, 'activity_type' => 'reviewed']);
        $theirs = Activity::create(['user_id' => $other->id, 'msgId' => 2002, 'activity_type' => 'approved']);

        Livewire::actingAs($actor)
            ->test(BoardActivity::class)
            ->filterTable('user_id', $actor->id)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_board_activity_searches_by_activity_type(): void
    {
        $user = $this->actor();

        $reviewed = Activity::create(['user_id' => $user->id, 'msgId' => 1001, 'activity_type' => 'reviewed']);
        $approved = Activity::create(['user_id' => $user->id, 'msgId' => 2002, 'activity_type' => 'approved']);

        Livewire::actingAs($user)
            ->test(BoardActivity::class)
            ->searchTable('approved')
            ->assertCanSeeTableRecords([$approved])
            ->assertCanNotSeeTableRecords([$reviewed]);
    }
}
