<?php

declare(strict_types=1);

namespace App\Services\Observability;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Checks whether anything is actually listening on the configured OTLP
 * endpoint, so the admin page can tell an operator "nothing is there yet, set
 * up a collector" instead of enabling tracing that silently goes nowhere.
 *
 * We deliberately do NOT assume a Grafana Alloy agent is already running. Alloy
 * is the recommended topology, but it is the operator's infrastructure, not
 * something this application installs or controls.
 */
class TraceEndpointProbe
{
    public const REACHABLE = 'reachable';

    public const REFUSED = 'refused';

    public const UNAUTHORIZED = 'unauthorized';

    public const ERROR = 'error';

    /**
     * Posts an empty OTLP trace payload. A collector answers 2xx (or 4xx for a
     * malformed body); a connection error means nothing is listening.
     *
     * @param  array<string, mixed>  $tracing
     * @return array{status: string, message: string, ms: int, httpStatus: ?int}
     */
    public function probe(array $tracing): array
    {
        $exporter = $tracing['exporter'];
        $url = SpanExporterFactory::tracesEndpoint((string) $exporter['endpoint']);
        $start = hrtime(true);

        try {
            $response = Http::withHeaders(array_merge(
                ['Content-Type' => 'application/json'],
                $exporter['headers'] ?? []
            ))
                ->connectTimeout((float) ($exporter['connect_timeout'] ?? 0.5))
                ->timeout((float) ($exporter['timeout'] ?? 2.0))
                ->withOptions(['http_errors' => false])
                // An empty resourceSpans list is a valid, no-op OTLP payload.
                ->post($url, ['resourceSpans' => []]);

            $ms = $this->elapsed($start);
            $status = $response->status();

            if ($status === 401 || $status === 403) {
                return [
                    'status' => self::UNAUTHORIZED,
                    'message' => "The collector at {$url} rejected the credentials (HTTP {$status}). "
                        .'Check the auth username and token.',
                    'ms' => $ms,
                    'httpStatus' => $status,
                ];
            }

            if ($status >= 200 && $status < 500) {
                return [
                    'status' => self::REACHABLE,
                    'message' => "A collector responded at {$url} (HTTP {$status}, {$ms}ms).",
                    'ms' => $ms,
                    'httpStatus' => $status,
                ];
            }

            return [
                'status' => self::ERROR,
                'message' => "The collector at {$url} returned HTTP {$status}.",
                'ms' => $ms,
                'httpStatus' => $status,
            ];
        } catch (Throwable $e) {
            return [
                'status' => self::REFUSED,
                'message' => "Nothing is listening at {$url} ({$e->getMessage()}). "
                    .'Tracing will stay inert until an OpenTelemetry collector — normally a '
                    .'Grafana Alloy agent with an otelcol.receiver.otlp block — is running and '
                    .'reachable from this server. See docs/observability.md for the Alloy config.',
                'ms' => $this->elapsed($start),
                'httpStatus' => null,
            ];
        }
    }

    private function elapsed(int $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}
