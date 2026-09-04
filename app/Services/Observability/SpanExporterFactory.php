<?php

declare(strict_types=1);

namespace App\Services\Observability;

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

/**
 * Builds the OTLP/HTTP span exporter. Isolated behind its own class so tests
 * can bind an in-memory exporter instead.
 */
class SpanExporterFactory
{
    /**
     * @param  array<string, mixed>  $tracing
     */
    public function make(array $tracing): SpanExporterInterface
    {
        $exporter = $tracing['exporter'];

        return new SpanExporter(
            (new OtlpHttpTransportFactory)->create(
                endpoint: self::tracesEndpoint((string) $exporter['endpoint']),
                contentType: ($exporter['protocol'] ?? 'http/protobuf') === 'http/json'
                    ? 'application/json'
                    : 'application/x-protobuf',
                headers: $exporter['headers'] ?? [],
                compression: ($exporter['compression'] ?? 'none') === 'gzip' ? 'gzip' : null,
                timeout: (float) ($exporter['timeout'] ?? 2.0),
                retryDelay: 100,
                // Default is 3. Against a dead collector that turns a 2s
                // timeout into ~6s of added latency for zero benefit, since the
                // collector is meant to be on localhost.
                maxRetries: (int) ($exporter['max_retries'] ?? 0),
            )
        );
    }

    /**
     * OTEL_EXPORTER_OTLP_ENDPOINT semantics: the configured value is a base
     * URL, and the signal path is appended.
     */
    public static function tracesEndpoint(string $endpoint): string
    {
        $endpoint = rtrim(trim($endpoint), '/');

        return str_ends_with($endpoint, '/v1/traces') ? $endpoint : $endpoint.'/v1/traces';
    }
}
