<?php

declare(strict_types=1);

namespace App\Database;

use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Support\Facades\Cache;
use PDOException;
use Throwable;

/**
 * Every SQL Server connection in the application, with a bounded connect and a short memory
 * of servers that are down.
 *
 * An unreachable Intelligent Series server used to make the whole application unusable until
 * its containers were restarted. Three things compounded:
 *
 *   - No LoginTimeout was set anywhere, so ODBC Driver 18 waited its default ~16.5s before
 *     giving up on a server that silently drops packets.
 *   - Laravel lists "Login timeout expired" among its lost-connection messages, so each failed
 *     query reconnected and tried again: ~33s per query.
 *   - Sail serves through `artisan serve` with one worker, and the dashboard polls every five
 *     seconds. Requests arrived faster than they could fail, every other URL queued behind
 *     them, and closing the tab did not help because the queued ones still ran.
 *
 * This is bound as `db.connector.sqlsrv`, so it covers all fifteen-odd places that configure a
 * SQL Server connection by hand without any of them changing.
 *
 * It does two things. It defaults `login_timeout` so a dead server costs seconds rather than
 * tens of seconds. And once a server has proved unreachable it remembers that for a short
 * window and fails the next attempts immediately, so a page polling a dead server stops
 * holding a worker at all.
 *
 * Only reachability failures trip it. A wrong password is a different problem with a
 * different fix, and someone correcting it at System -> Data Sources should see the real
 * error straight away rather than a cached "unreachable".
 */
class GuardedSqlServerConnector extends SqlServerConnector
{
    /** Seconds a connect may take before ODBC gives up. */
    public const LOGIN_TIMEOUT = 5;

    /** Seconds an unreachable server is failed fast before it is tried again. */
    public const COOL_DOWN = 30;

    /**
     * SQLSTATEs and driver text that mean the server could not be reached at all, as
     * opposed to reached and refused.
     */
    private const UNREACHABLE = [
        'HYT00',
        '08001',
        'Login timeout expired',
        'TCP Provider',
        'Communication link failure',
        'Named Pipes Provider',
    ];

    public function connect(array $config)
    {
        $config['login_timeout'] ??= self::LOGIN_TIMEOUT;

        $key = self::key($config);

        if ($this->isCoolingDown($key)) {
            throw new PDOException(sprintf(
                'SQL Server at %s was unreachable within the last %d seconds, so this attempt was '
                .'not made. It will be tried again after the cool-down.',
                self::endpoint($config),
                self::COOL_DOWN,
            ));
        }

        try {
            $connection = parent::connect($config);
        } catch (Throwable $e) {
            if (self::isUnreachable($e)) {
                $this->coolDown($key);
            }

            throw $e;
        }

        $this->forget($key);

        return $connection;
    }

    /**
     * Laravel's connector retries a failed connect once when the error looks like a lost
     * connection, and "Login timeout expired" does. Against a server that is not there, that
     * retry is a second full timeout spent learning nothing -- and because the query layer
     * retries too, and its retry connects again, the two compound: four connect attempts per
     * query, 63.5 seconds measured against a server that drops packets. So a reachability
     * failure is thrown straight away. Any other lost-connection error keeps its retry.
     */
    protected function tryAgainIfCausedByLostConnection(Throwable $e, $dsn, $username, #[\SensitiveParameter] $password, $options)
    {
        if (self::isUnreachable($e)) {
            throw $e;
        }

        return parent::tryAgainIfCausedByLostConnection($e, $dsn, $username, $password, $options);
    }

    /**
     * Whether a connect failure was the server not being there, rather than refusing us.
     */
    public static function isUnreachable(Throwable $e): bool
    {
        $text = $e->getMessage().' '.(string) $e->getCode();

        foreach (self::UNREACHABLE as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    public static function key(array $config): string
    {
        return 'sqlsrv-unreachable:'.self::endpoint($config);
    }

    private static function endpoint(array $config): string
    {
        return ($config['host'] ?? '').','.($config['port'] ?? '1433');
    }

    // The cache is a convenience here, never a dependency. If it is itself unavailable --
    // Redis down alongside SQL Server is not unusual in a broken dev stack -- connecting
    // proceeds exactly as it would without this class.

    private function isCoolingDown(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (Throwable) {
            return false;
        }
    }

    private function coolDown(string $key): void
    {
        try {
            Cache::put($key, true, self::COOL_DOWN);
        } catch (Throwable) {
            // See above.
        }
    }

    private function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable) {
            // See above.
        }
    }
}
