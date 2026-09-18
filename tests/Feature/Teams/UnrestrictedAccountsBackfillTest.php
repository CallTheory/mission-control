<?php

declare(strict_types=1);

namespace Tests\Feature\Teams;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The backfill's only job is to make existing behaviour explicit: a team with no
 * allow-lists could already see every account, so it is marked as such and nothing
 * changes for it. A team that was scoped stays scoped.
 *
 * The migration is re-run here against rows inserted in the pre-migration shape, because
 * asserting on the state of already-migrated rows would prove nothing about the UPDATE.
 */
class UnrestrictedAccountsBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_only_the_teams_that_had_no_lists(): void
    {
        $migration = require database_path(
            'migrations/2026_09_18_000007_add_unrestricted_accounts_to_teams.php'
        );

        $migration->down();
        $this->assertFalse(Schema::hasColumn('teams', 'unrestricted_accounts'));

        $cases = [
            'both null' => [null, null, true],
            'both empty strings' => ['', '', true],
            'whitespace only' => ["  \n ", '   ', true],
            'accounts listed' => ['1000-2000', null, false],
            'billing listed' => [null, '500', false],
            'both listed' => ['1000', '500', false],
        ];

        $ids = [];

        foreach ($cases as $label => [$accounts, $billing, $_]) {
            $ids[$label] = DB::table('teams')->insertGetId([
                'user_id' => 1,
                'name' => $label,
                'personal_team' => false,
                'allowed_accounts' => $accounts,
                'allowed_billing' => $billing,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration->up();

        foreach ($cases as $label => [$_a, $_b, $expected]) {
            $this->assertSame(
                $expected,
                (bool) DB::table('teams')->where('id', $ids[$label])->value('unrestricted_accounts'),
                "team with {$label} should ".($expected ? '' : 'not ').'be marked unrestricted',
            );
        }
    }
}
