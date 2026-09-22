<?php

declare(strict_types=1);

namespace Tests\Feature\CallLog;

use App\Livewire\Utilities\CallLog;
use App\Models\Stats\Calls\CallLog as CallLogStats;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The call log's agent column and agent filter, both of which were silently dead.
 *
 * The Filament conversion replaced a Blade cell that parsed the query's `AgentList`
 * with `TextColumn::make('Agents')` — a key the query has never returned, so every
 * call showed no agents — and changed the agent select from `agtId => Name` to
 * `Name => Name`, so the filter submitted a display name where the SQL matched on
 * agent ids and therefore matched nothing.
 *
 * Neither failed loudly: an empty column looks like a call with no agents, and the
 * call log swallows query exceptions and renders an empty table. These assert the
 * contract between the query's SELECT list and the things that read it.
 */
class CallLogAgentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build the query object without running it.
     *
     * Stat::__construct() executes the T-SQL against the Intelligent Series database,
     * which is not reachable from a test, so the constructor is bypassed and only the
     * properties tsql() reads are set. These assert the SQL the class generates, which
     * is the part that was wrong.
     */
    private function stats(?string $agent = null): CallLogStats
    {
        $stats = (new ReflectionClass(CallLogStats::class))->newInstanceWithoutConstructor();

        $stats->start_date = '2026-09-01 00:00:00';
        $stats->end_date = '2026-09-02 00:00:00';
        $stats->tz = 'UTC';
        $stats->agent = $agent;
        $stats->sortBy = 'statCallStart.Stamp';
        $stats->sortDirection = 'desc';
        $stats->parameters = [];

        foreach (['allowed_accounts', 'allowed_billing'] as $private) {
            $property = new ReflectionProperty(CallLogStats::class, $private);
            $property->setValue($stats, '');
        }

        $stats->validateParams();

        return $stats;
    }

    public function test_the_query_returns_the_agent_column_the_table_renders(): void
    {
        // The actual bug class: a column key that no SELECT alias produces.
        $this->assertStringContainsString('as [AgentNames]', $this->stats()->tsql());
    }

    public function test_every_call_log_column_key_exists_in_the_query(): void
    {
        // The generalised form of the bug. A column whose key no SELECT alias produces
        // renders blank for every row for ever, with no error anywhere — so assert
        // against the table the screen actually builds rather than a list kept by hand.
        $user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false, 'utility_call_lookup' => true]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        $columns = Livewire::actingAs($user->fresh())
            ->test(CallLog::class)
            ->instance()
            ->getTable()
            ->getColumns();

        $sql = $this->stats()->tsql();

        foreach ($columns as $column) {
            // A column that computes its own state needs no alias — `assets` builds a
            // badge list out of three other fields.
            $stateUsing = new ReflectionProperty($column, 'getStateUsing');

            if ($stateUsing->getValue($column) !== null) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/as \[?'.preg_quote($column->getName(), '/').'\]?/i',
                $sql,
                "The call log renders a [{$column->getName()}] column, but the query never selects one."
            );
        }
    }

    public function test_the_agent_filter_matches_the_agent_id_exactly(): void
    {
        $stats = $this->stats('5');
        $sql = $stats->tsql();

        $this->assertStringContainsString('sct.agtId = ?', $sql);
        $this->assertSame(5, $stats->parameters['agent']);
    }

    public function test_the_agent_filter_does_not_substring_match_ids(): void
    {
        // The previous form aggregated the call's agent ids into a string and did
        // LIKE '%5%', so agent 5 matched calls handled by agents 15, 51 and 52.
        $sql = $this->stats('5')->tsql();

        $this->assertStringNotContainsString("STRING_AGG(agtId, ',')", $sql);
        $this->assertStringNotContainsString("LIKE CONCAT ('%', ?, '%')", $sql);
    }

    public function test_no_agent_filter_leaves_the_query_unfiltered(): void
    {
        $stats = $this->stats();

        $this->assertStringNotContainsString('sct.agtId = ?', $stats->tsql());
        $this->assertArrayNotHasKey('agent', $stats->parameters);
    }

    public function test_agent_list_keeps_its_packed_shape_for_the_csv_export(): void
    {
        // CsvExport writes AgentList out verbatim, so its format is a contract even
        // though the screen no longer parses it.
        $this->assertStringContainsString('as [AgentList]', $this->stats()->tsql());
    }

    public function test_the_agent_select_submits_an_agent_id(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false, 'utility_call_lookup' => true]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        $component = Livewire::actingAs($user->fresh())->test(CallLog::class);

        // The agent listing comes from the Intelligent Series database, which is not
        // reachable in tests; what matters is the shape the options are built in.
        $component->set('agents', [
            (object) ['agtId' => 42, 'Name' => 'Jane Smith', 'Initials' => 'JS'],
        ]);

        $schema = (new ReflectionMethod($component->instance(), 'callLogFilterSchema'))
            ->invoke($component->instance());

        $options = collect($schema)
            ->first(fn ($field): bool => method_exists($field, 'getName') && $field->getName() === 'agent')
            ->getOptions();

        // Keyed by id, labelled by name — submitting the name matched nothing.
        $this->assertSame(['42' => 'Jane Smith'], $options);
    }
}
