<?php

declare(strict_types=1);

namespace App\Services\Observability;

use App\Models\System\Settings;
use Throwable;

/**
 * Resolves the observability configuration from three layers, in order:
 *
 *   1. hardcoded safe defaults (everything off)
 *   2. the `settings` table row, edited in System -> Observability
 *   3. env vars, which WIN when explicitly set
 *
 * Env wins last so an operator always has a kill switch that needs no database
 * access, and so CI/staging can force a setting. The admin UI surfaces a banner
 * when an env override is in effect, otherwise the toggle would appear to do
 * nothing.
 *
 * This runs during application boot, on every request and every artisan
 * command — including `migrate` on an empty database and `package:discover`
 * during `composer install`. It must therefore never throw.
 */
final class ObservabilityConfig
{
    /** Memoized for the life of the process. */
    private static ?array $resolved = null;

    public static function errorsEnabled(): bool
    {
        return (bool) (self::resolve()['errors']['enabled'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function errors(): array
    {
        return self::resolve()['errors'];
    }

    public static function tracingEnabled(): bool
    {
        return (bool) (self::resolve()['tracing']['enabled'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public static function tracing(): array
    {
        return self::resolve()['tracing'];
    }

    /**
     * Drop the memo. Called after an admin saves, and from tests — a memoized
     * value leaking between tests silently changes their behavior.
     */
    public static function flush(): void
    {
        self::$resolved = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolve(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $config = config('observability') ?? [];
        $envErrorsEnabled = $config['errors']['enabled'] ?? null;
        $envTracingEnabled = $config['tracing']['enabled'] ?? null;

        // Fast path: an explicit env "off" means we never touch the database.
        $row = ($envErrorsEnabled === false && $envTracingEnabled === false)
            ? null
            : self::settingsRow();

        return self::$resolved = [
            'errors' => [
                'enabled' => self::pick(
                    $envErrorsEnabled,
                    $row?->observability_errors_enabled,
                    false
                ),
                'dsn' => self::pick(
                    $config['errors']['dsn'] ?? null,
                    $row?->observability_errors_dsn,
                    null
                ),
                'environment' => self::pick(
                    $config['environment'] ?? null,
                    $row?->observability_environment,
                    config('app.env')
                ),
                'release' => self::pick(
                    $config['release'] ?? null,
                    $row?->observability_release,
                    null
                ),
                'sample_rate' => (float) self::pick(
                    $config['errors']['sample_rate'] ?? null,
                    $row?->observability_errors_sample_rate,
                    1.0
                ),
                'send_default_pii' => (bool) ($config['errors']['send_default_pii'] ?? false),
                'breadcrumbs' => $config['errors']['breadcrumbs'] ?? [],
                'ignore_exceptions' => $config['errors']['ignore_exceptions'] ?? [],
                // True when env is pinning the toggle, so the UI can say so.
                'overridden_by_env' => $envErrorsEnabled !== null,
            ],
            'tracing' => [
                'enabled' => self::pick($envTracingEnabled, null, false),
                'overridden_by_env' => $envTracingEnabled !== null,
            ],
        ];
    }

    /**
     * env value if set, else the database value if set, else the default.
     */
    private static function pick(mixed $env, mixed $db, mixed $default): mixed
    {
        if ($env !== null && $env !== '') {
            return $env;
        }

        if ($db !== null && $db !== '') {
            return $db;
        }

        return $default;
    }

    /**
     * The settings singleton, or null when it cannot be read.
     *
     * Catches Throwable rather than QueryException on purpose: a missing
     * `settings` table (fresh install, migrate:fresh) throws QueryException,
     * but a missing APP_KEY makes the EncryptedSerialized cast throw something
     * else entirely, and an uncaught throw here is fatal at boot.
     */
    private static function settingsRow(): ?Settings
    {
        try {
            if (! app()->bound('db')) {
                return null;
            }

            return Settings::query()->first();
        } catch (Throwable) {
            return null;
        }
    }
}
