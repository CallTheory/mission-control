<?php

declare(strict_types=1);

namespace Tests\Unit\Stats;

use App\Models\Stats\Calls\CallLog;
use App\Models\Stats\Clients\Overview;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

/**
 * ORDER BY cannot be bound as a parameter, so both of these classes interpolate the
 * sort column and direction into their T-SQL. The values reach them from Livewire
 * components whose public properties the browser can set directly, so the allow-list
 * is the only thing standing between a crafted request and arbitrary SQL.
 *
 * These call the resolvers directly rather than constructing the Stat, because the
 * constructor opens a SQL Server connection that no test environment has.
 */
class OrderByAllowListTest extends TestCase
{
    private function resolve(string $class, string $method, mixed $value): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke(null, $value);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function injectionAttempts(): array
    {
        return [
            'union select' => ['ClientNumber; select * from sysobjects --'],
            'stacked statement' => ['1; drop table cltClients --'],
            'comment terminator' => ['ClientName --'],
            'subquery' => ['(select top 1 password from users)'],
            'unknown column' => ['NotAColumn'],
            'empty' => [''],
        ];
    }

    // ------------------------------------------------------------------
    // Clients\Overview
    // ------------------------------------------------------------------

    #[DataProvider('injectionAttempts')]
    public function test_clients_overview_rejects_anything_off_the_allow_list(string $attempt): void
    {
        $this->assertSame(
            'ClientNumber',
            $this->resolve(Overview::class, 'resolveOrderBy', $attempt),
        );
    }

    public function test_clients_overview_accepts_its_allow_listed_columns(): void
    {
        foreach (Overview::ORDERABLE as $column) {
            $this->assertSame($column, $this->resolve(Overview::class, 'resolveOrderBy', $column));
        }
    }

    public function test_clients_overview_direction_is_only_ever_asc_or_desc(): void
    {
        $this->assertSame('desc', $this->resolve(Overview::class, 'resolveOrderDirection', 'desc'));
        $this->assertSame('desc', $this->resolve(Overview::class, 'resolveOrderDirection', 'DESC'));
        $this->assertSame('asc', $this->resolve(Overview::class, 'resolveOrderDirection', 'asc'));
        $this->assertSame('asc', $this->resolve(Overview::class, 'resolveOrderDirection', 'asc; drop table x'));
        $this->assertSame('asc', $this->resolve(Overview::class, 'resolveOrderDirection', ''));
    }

    // ------------------------------------------------------------------
    // Calls\CallLog
    // ------------------------------------------------------------------

    #[DataProvider('injectionAttempts')]
    public function test_call_log_rejects_anything_off_the_allow_list(string $attempt): void
    {
        $this->assertSame(
            'statCallStart.Stamp',
            $this->resolve(CallLog::class, 'resolveSortBy', $attempt),
        );
    }

    public function test_call_log_accepts_its_allow_listed_columns(): void
    {
        foreach (CallLog::SORTABLE as $column) {
            $this->assertSame($column, $this->resolve(CallLog::class, 'resolveSortBy', $column));
        }
    }

    public function test_call_log_direction_is_only_ever_asc_or_desc(): void
    {
        $this->assertSame('asc', $this->resolve(CallLog::class, 'resolveSortDirection', 'asc'));
        $this->assertSame('desc', $this->resolve(CallLog::class, 'resolveSortDirection', 'desc'));
        $this->assertSame('desc', $this->resolve(CallLog::class, 'resolveSortDirection', 'desc, (select 1)'));
        $this->assertSame('desc', $this->resolve(CallLog::class, 'resolveSortDirection', null));
    }
}
