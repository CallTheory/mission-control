<?php

declare(strict_types=1);

namespace App\Services\Faxing;

/**
 * One RingCentral extension Mission Control can send faxes as.
 *
 * The key is what everything else refers to it by: rate-limit state, send locks, the
 * pending_faxes row, the log. "primary" is the JWT on the data source; additional senders
 * are "sender-{id}".
 */
final class RingCentralExtension
{
    public const PRIMARY = 'primary';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $jwt,
        public readonly ?string $extensionId = null,
    ) {}

    public static function rowKey(int $id): string
    {
        return "sender-{$id}";
    }

    public function isPrimary(): bool
    {
        return $this->key === self::PRIMARY;
    }
}
