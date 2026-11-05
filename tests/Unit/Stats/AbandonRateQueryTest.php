<?php

declare(strict_types=1);

namespace Tests\Unit\Stats;

use App\Models\Stats\Aggregate\AbandonRate;
use ReflectionClass;
use Tests\TestCase;

/**
 * The abandon rate tile used to copy its dates into declared variables and count
 * statCallEnd twice. SQL Server cannot see a local variable's value when it plans, so
 * on a large statCallEnd it scanned the clustered index rather than seeking on Stamp,
 * and a single open dashboard was enough to saturate a production server's reads.
 *
 * Built without its constructor, which opens a SQL Server connection that no test
 * environment has.
 */
class AbandonRateQueryTest extends TestCase
{
    private function tsql(string $accounts = '', string $billing = ''): string
    {
        $reflection = new ReflectionClass(AbandonRate::class);
        $stat = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('allowed_accounts')->setValue($stat, $accounts);
        $reflection->getProperty('allowed_billing')->setValue($stat, $billing);

        return $stat->tsql();
    }

    public function test_the_dates_are_bound_into_the_predicate_not_declared(): void
    {
        $sql = $this->tsql();

        $this->assertStringNotContainsStringIgnoringCase('declare', $sql);
        $this->assertStringNotContainsString('@', $sql);
        $this->assertStringContainsString('statCallEnd.Stamp between ? and ?', $sql);
        $this->assertSame(2, substr_count($sql, '?'));
    }

    public function test_it_reads_stat_call_end_once(): void
    {
        $this->assertSame(1, substr_count(strtolower($this->tsql()), 'from statcallend'));
    }

    public function test_the_team_filters_still_apply(): void
    {
        $sql = $this->tsql("100-199\n250", '7');

        $this->assertStringContainsString('cltClients.ClientNumber between 100 and 199', $sql);
        $this->assertStringContainsString('cltClients.ClientNumber in (250)', $sql);
        $this->assertStringContainsString('cltClients.BillingCode in (7)', $sql);
    }
}
