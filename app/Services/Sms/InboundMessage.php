<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * One inbound SMS, normalised out of whatever shape the carrier posted.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $from,
        public string $to,
        public string $body,
        public string $providerMessageId,
    ) {}
}
