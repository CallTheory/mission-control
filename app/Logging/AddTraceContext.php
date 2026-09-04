<?php

declare(strict_types=1);

namespace App\Logging;

use App\Services\Observability\Tracing;
use Monolog\LogRecord;
use Throwable;

/**
 * Monolog tap that stamps the active trace and span id onto every log record,
 * so a trace id from a log line can be pasted straight into Tempo.
 *
 * A tap rather than Log::withContext() because it works identically in web,
 * queue-worker and console processes with no per-request reset.
 */
class AddTraceContext
{
    public function __invoke($logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record) {
                try {
                    $context = app(Tracing::class)->currentTraceContext();

                    if ($context !== null) {
                        $record->extra['trace_id'] = $context['trace_id'];
                        $record->extra['span_id'] = $context['span_id'];
                    }
                } catch (Throwable) {
                    // A throwing processor would break logging itself.
                }

                return $record;
            });
        }
    }
}
