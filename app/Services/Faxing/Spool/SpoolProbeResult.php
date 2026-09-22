<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

/**
 * The outcome of testing a spool source, in the shape the admin screen renders.
 *
 * Modelled on App\Services\Observability\TraceEndpointProbe: a typed status plus an
 * actionable message beats a raw exception string when the reader is an operator deciding
 * whether they typed the password wrongly or the server is simply off.
 */
final readonly class SpoolProbeResult
{
    public const REACHABLE = 'reachable';

    public const UNAUTHORIZED = 'unauthorized';

    public const UNREACHABLE = 'unreachable';

    public const MISCONFIGURED = 'misconfigured';

    /**
     * @param  array<string, int>  $folderCounts
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public string $status,
        public string $message,
        public int $elapsedMs,
        public array $folderCounts = [],
        public bool $writable = false,
        public array $warnings = [],
    ) {}

    public function ok(): bool
    {
        return $this->status === self::REACHABLE;
    }
}
