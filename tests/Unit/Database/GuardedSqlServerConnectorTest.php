<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\GuardedSqlServerConnector;
use Illuminate\Support\Facades\Cache;
use PDO;
use PDOException;
use RuntimeException;
use Tests\TestCase;

/**
 * An unreachable SQL Server used to wedge the whole application: 63.5 seconds per query
 * measured against a host that drops packets, and with `artisan serve`'s single worker and a
 * dashboard polling every five seconds, nothing else could be served until the containers
 * were restarted. These pin each part of the fix without needing a real dead server.
 */
class GuardedSqlServerConnectorTest extends TestCase
{
    private const CONFIG = ['driver' => 'sqlsrv', 'host' => 'is.example.test', 'port' => 1433, 'database' => 'x'];

    private const UNREACHABLE = 'SQLSTATE[HYT00]: [Microsoft][ODBC Driver 18 for SQL Server]Login timeout expired';

    private const BAD_PASSWORD = "SQLSTATE[28000]: [Microsoft][ODBC Driver 18 for SQL Server][SQL Server]Login failed for user 'u'.";

    /**
     * A connector whose only change is that the network is replaced: each attempt records
     * the DSN it was given and then fails or succeeds as told.
     */
    private function connector(?string $failWith): GuardedSqlServerConnector
    {
        return new class($failWith) extends GuardedSqlServerConnector
        {
            public int $attempts = 0;

            public string $dsn = '';

            public function __construct(private ?string $failWith) {}

            // Laravel builds a sqlsrv DSN only when pdo_sqlsrv is loaded and falls back to
            // dblib otherwise, which carries no LoginTimeout. The app and the CI image run on
            // pdo_sqlsrv; a developer's host PHP often has only sqlite. Pinned so the result
            // does not depend on which machine runs it.
            protected function getAvailableDrivers()
            {
                return ['sqlsrv'];
            }

            protected function createPdoConnection($dsn, $username, #[\SensitiveParameter] $password, $options)
            {
                $this->attempts++;
                $this->dsn = $dsn;

                if ($this->failWith !== null) {
                    throw new PDOException($this->failWith);
                }

                return $this->createStub(PDO::class);
            }

            private function createStub(string $class): PDO
            {
                return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            }
        };
    }

    private function attempt(GuardedSqlServerConnector $connector): void
    {
        try {
            $connector->connect(self::CONFIG);
        } catch (PDOException) {
            // The assertions are about what happened, not that it threw.
        }
    }

    public function test_it_is_the_connector_every_sql_server_connection_uses(): void
    {
        $this->assertInstanceOf(GuardedSqlServerConnector::class, app('db.connector.sqlsrv'));
    }

    /** ODBC Driver 18's default is about 16.5 seconds against a server that drops packets. */
    public function test_a_connect_is_bounded_by_a_login_timeout(): void
    {
        $connector = $this->connector(failWith: null);

        $connector->connect(self::CONFIG);

        $this->assertStringContainsString('LoginTimeout='.GuardedSqlServerConnector::LOGIN_TIMEOUT, $connector->dsn);
    }

    public function test_a_connection_that_sets_its_own_timeout_keeps_it(): void
    {
        $connector = $this->connector(failWith: null);

        $connector->connect([...self::CONFIG, 'login_timeout' => 30]);

        $this->assertStringContainsString('LoginTimeout=30', $connector->dsn);
    }

    /**
     * Laravel's connector retries once on anything that looks like a lost connection, and a
     * login timeout does. Against a dead server that retry is a second full timeout.
     */
    public function test_an_unreachable_server_is_tried_once_not_twice(): void
    {
        $connector = $this->connector(failWith: self::UNREACHABLE);

        $this->attempt($connector);

        $this->assertSame(1, $connector->attempts);
    }

    public function test_after_an_unreachable_server_the_next_attempt_fails_without_connecting(): void
    {
        $first = $this->connector(failWith: self::UNREACHABLE);
        $this->attempt($first);

        $second = $this->connector(failWith: null);

        try {
            $second->connect(self::CONFIG);
            $this->fail('A server inside its cool-down was connected to.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('was unreachable within the last', $e->getMessage());
        }

        $this->assertSame(0, $second->attempts);
    }

    /**
     * A wrong password is not an outage. Someone fixing it at System -> Data Sources has to
     * see the real error on the next try, not a cached "unreachable".
     */
    public function test_a_refused_login_does_not_start_a_cool_down(): void
    {
        $this->attempt($this->connector(failWith: self::BAD_PASSWORD));

        $next = $this->connector(failWith: null);
        $next->connect(self::CONFIG);

        $this->assertSame(1, $next->attempts);
    }

    public function test_a_different_server_is_not_held_back_by_another_s_outage(): void
    {
        $this->attempt($this->connector(failWith: self::UNREACHABLE));

        $other = $this->connector(failWith: null);
        $other->connect([...self::CONFIG, 'host' => 'other.example.test']);

        $this->assertSame(1, $other->attempts);
    }

    public function test_once_the_cool_down_passes_the_server_is_tried_again(): void
    {
        $this->attempt($this->connector(failWith: self::UNREACHABLE));

        $this->travel(GuardedSqlServerConnector::COOL_DOWN + 1)->seconds();

        $next = $this->connector(failWith: null);
        $next->connect(self::CONFIG);

        $this->assertSame(1, $next->attempts);
    }

    /**
     * Redis down alongside SQL Server is not unusual in a broken dev stack. The cache is a
     * convenience, so without it connecting behaves as if this class did not exist.
     */
    public function test_an_unavailable_cache_does_not_stop_a_connection(): void
    {
        Cache::shouldReceive('has')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));
        Cache::shouldReceive('forget')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));

        $connector = $this->connector(failWith: null);
        $connector->connect(self::CONFIG);

        $this->assertSame(1, $connector->attempts);
    }
}
