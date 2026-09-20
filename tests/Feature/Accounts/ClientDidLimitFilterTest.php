<?php

declare(strict_types=1);

namespace Tests\Feature\Accounts;

use App\Models\Stats\Clients\Overview;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * The DID limit filter on the account listing.
 *
 * Overview's constructor reaches for the Amtelco data source, which is not
 * available in tests, so these build the object without it and exercise tsql()
 * directly -- the SQL text and its bindings are the whole contract here.
 */
class ClientDidLimitFilterTest extends TestCase
{
    /**
     * @param  array<string, string>  $config
     * @return array{string, array<string, mixed>}
     */
    private function build(array $config): array
    {
        $reflection = new ReflectionClass(Overview::class);
        $overview = $reflection->newInstanceWithoutConstructor();

        $defaults = [
            'client_name' => '', 'client_number' => '', 'billing_code' => '',
            'allowed_accounts' => '', 'allowed_billing' => '',
            'account_setting' => '', 'account_setting_value' => '', 'client_source' => '',
            'order_by' => 'ClientNumber', 'order_direction' => 'asc',
            'did_limit' => (string) ($config['did_limit'] ?? ''),
        ];

        foreach ($defaults as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setAccessible(true);
            $property->setValue($overview, $config[$name] ?? $value);
        }

        $resolve = $reflection->getMethod('resolveDidLimitOperator');
        $resolve->setAccessible(true);
        $operator = $reflection->getProperty('did_limit_operator');
        $operator->setAccessible(true);
        $operator->setValue($overview, $resolve->invoke(null, $config['did_limit_operator'] ?? null));

        $parameters = $reflection->getProperty('parameters');
        $parameters->setAccessible(true);
        $parameters->setValue($overview, []);

        return [$overview->tsql(), $parameters->getValue($overview)];
    }

    public function test_the_limit_is_selected_so_the_listing_can_show_it(): void
    {
        [$sql] = $this->build([]);

        $this->assertStringContainsString('cltClients.DIDLimit', $sql);
    }

    public function test_no_operator_means_no_filter(): void
    {
        [$sql, $parameters] = $this->build([]);

        $this->assertStringNotContainsString('DIDLimit =', $sql);
        $this->assertStringNotContainsString('DIDLimit <>', $sql);
        $this->assertSame([], $parameters);
    }

    public function test_equals_zero_filters_for_unlimited_accounts(): void
    {
        // 0 means unlimited, so it has to survive as a real filter value rather
        // than being treated as "nothing entered".
        [$sql, $parameters] = $this->build(['did_limit_operator' => 'eq', 'did_limit' => '0']);

        $this->assertMatchesRegularExpression('/cltClients\.DIDLimit\s*=\s*\?/', $sql);
        $this->assertSame(['did_limit' => 0], $parameters);
    }

    public function test_not_equal_uses_the_sql_inequality_operator(): void
    {
        [$sql, $parameters] = $this->build(['did_limit_operator' => 'ne', 'did_limit' => '0']);

        $this->assertMatchesRegularExpression('/cltClients\.DIDLimit\s*<>\s*\?/', $sql);
        $this->assertSame(['did_limit' => 0], $parameters);
    }

    public function test_the_limit_is_bound_not_interpolated(): void
    {
        [$sql, $parameters] = $this->build(['did_limit_operator' => 'eq', 'did_limit' => '4']);

        $this->assertStringNotContainsString('DIDLimit = 4', $sql);
        $this->assertSame(['did_limit' => 4], $parameters);
    }

    public function test_it_combines_with_the_other_filters(): void
    {
        [$sql, $parameters] = $this->build([
            'client_name' => 'acme',
            'did_limit_operator' => 'eq',
            'did_limit' => '2',
        ]);

        $this->assertStringContainsString('ClientName like', $sql);
        $this->assertMatchesRegularExpression('/and cltClients\.DIDLimit\s*=\s*\?/', $sql);
        $this->assertSame(['client_name_filter' => 'acme', 'did_limit' => 2], $parameters);
    }

    /**
     * The operator is interpolated into the T-SQL because an operator cannot be
     * bound, so anything outside the allow-list has to drop the clause entirely.
     */
    #[DataProvider('hostileOperators')]
    public function test_an_operator_outside_the_allow_list_is_refused(string $operator): void
    {
        [$sql, $parameters] = $this->build(['did_limit_operator' => $operator, 'did_limit' => '4']);

        $this->assertStringNotContainsString('drop table', strtolower($sql));
        $this->assertStringNotContainsString('DIDLimit', substr($sql, (int) strpos($sql, 'from cltClients')));
        $this->assertSame([], $parameters);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostileOperators(): array
    {
        return [
            'sql injection' => ['=1; drop table cltClients--'],
            'or tautology' => ['= 1 or 1=1 --'],
            'unlisted comparison' => ['>'],
            'empty' => [''],
        ];
    }

    public function test_a_non_numeric_limit_drops_the_filter(): void
    {
        [$sql, $parameters] = $this->build(['did_limit_operator' => 'eq', 'did_limit' => '4; drop table x--']);

        $this->assertStringNotContainsString('drop table', strtolower($sql));
        $this->assertSame([], $parameters);
    }
}
