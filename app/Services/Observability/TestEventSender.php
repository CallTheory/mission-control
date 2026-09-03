<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Sentry\ClientBuilder;
use Sentry\Severity;
use Sentry\State\Hub;
use Sentry\Transport\ResultStatus;
use Throwable;

/**
 * Sends a single test event to a DSN using a throwaway client, so an operator
 * can verify connectivity from the admin page before saving.
 *
 * Injectable so tests can swap in a fake rather than making a real request.
 */
class TestEventSender
{
    /**
     * @return array{ok: bool, eventId: ?string, error: ?string, ms: int}
     */
    public function send(string $dsn, ?string $environment, ?string $release, ?int $userId = null): array
    {
        $start = hrtime(true);

        try {
            $client = ClientBuilder::create([
                'dsn' => $dsn,
                'environment' => $environment ?: config('app.env'),
                'release' => $release,
                'send_default_pii' => false,
                'max_request_body_size' => 'none',
                'default_integrations' => false,
                'http_connect_timeout' => 2,
                'http_timeout' => 5,
            ])->getClient();

            $hub = new Hub($client);
            $hub->configureScope(function ($scope) use ($userId) {
                $scope->setTag('test_event', 'true');
                $scope->setTag('triggered_by_user_id', (string) ($userId ?? 0));
            });

            $eventId = $hub->captureMessage(
                'Mission Control observability test event',
                Severity::info()
            );

            // flush() is synchronous in sentry/sentry 4.x and returns a Result.
            $result = $client->flush(5);
            $ok = $eventId !== null && $result->getStatus() === ResultStatus::success();

            return [
                'ok' => $ok,
                'eventId' => $eventId !== null ? (string) $eventId : null,
                'error' => $ok ? null : 'The event was not accepted ('.$result->getStatus().').',
                'ms' => $this->elapsed($start),
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'eventId' => null,
                'error' => $e->getMessage(),
                'ms' => $this->elapsed($start),
            ];
        }
    }

    private function elapsed(int $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
