<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Livewire\Accounts\Clients;
use App\Models\Team;
use App\Models\User;
use Filament\Schemas\Components\Grid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The account filter form keeps each comparison beside its number.
 *
 * "DID Limit / Simultaneous Calls" and "Source Count / Sources" only read as
 * sentences when the operator and its value sit together. Loose in the
 * three-column grid they wrapped against whatever field followed, and because
 * each number is only visible once its operator is chosen, the row reflowed the
 * moment you picked one. Each pair therefore gets a full-width two-column row.
 */
class ClientFilterLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function filterRows(bool $withOperators): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);
        $user = $user->fresh();

        $test = Livewire::actingAs($user)->test(Clients::class);

        if ($withOperators) {
            $test->set('tableFilters.account.did_limit_operator', 'eq');
            $test->set('tableFilters.account.source_count_operator', 'gt');
        }

        $components = $test->instance()->getTable()->getFilters()['account']->getSchema()->getComponents();

        $rows = [];

        foreach ($components as $component) {
            if ($component instanceof Grid) {
                $rows[] = array_map(
                    fn ($child): string => $child->getName(),
                    $component->getChildSchema()->getComponents()
                );
            }
        }

        return $rows;
    }

    public function test_each_comparison_pair_gets_its_own_row(): void
    {
        $this->assertSame(
            [['did_limit_operator', 'did_limit'], ['source_count_operator', 'source_count']],
            $this->filterRows(withOperators: true),
            'The operator and its number must share a row, and the two pairs must not share one.'
        );
    }

    public function test_the_rows_survive_the_numbers_being_hidden(): void
    {
        // With no operator chosen the numbers are hidden, but each pair must still
        // occupy its own row rather than collapsing into the fields around it.
        $this->assertSame(
            [['did_limit_operator'], ['source_count_operator']],
            $this->filterRows(withOperators: false)
        );
    }
}
