<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Services\Observability\TestEventSender;

/**
 * Container-bound stand-in so tests never make a real outbound request.
 * Follows the MockTwilioService convention rather than Http::fake(), which
 * would not intercept the Sentry SDK's own PSR-18 client anyway.
 */
class FakeTestEventSender extends TestEventSender
{
    public static bool $shouldSucceed = true;

    /** @var array<int, string> */
    public static array $sentTo = [];

    public function send(string $dsn, ?string $environment, ?string $release, ?int $userId = null): array
    {
        self::$sentTo[] = $dsn;

        return self::$shouldSucceed
            ? ['ok' => true, 'eventId' => 'abc123', 'error' => null, 'ms' => 12]
            : ['ok' => false, 'eventId' => null, 'error' => 'Connection refused.', 'ms' => 2];
    }
}
