<?php

declare(strict_types=1);

namespace Tests\Mocks;

use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;

/**
 * Collects events in memory instead of sending them.
 *
 * Http::fake() is deliberately not used for Sentry: the SDK uses its own PSR-18
 * client, so the facade fake would intercept nothing.
 */
class SpyTransport implements TransportInterface
{
    /** @var array<int, Event> */
    public static array $events = [];

    public static function reset(): void
    {
        self::$events = [];
    }

    public function send(Event $event): Result
    {
        self::$events[] = $event;

        return new Result(ResultStatus::success(), $event);
    }

    public function close(?int $timeout = null): Result
    {
        return new Result(ResultStatus::success());
    }
}
