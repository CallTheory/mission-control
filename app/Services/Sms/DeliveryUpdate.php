<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * One delivery receipt, normalised to the statuses `wctp_messages.status` uses.
 *
 * Carriers correlate receipts differently: Twilio calls a per-message URL that
 * carries our WCTP message id, Bandwidth echoes back the `tag` we sent, Com.io
 * only returns its own guid. So a receipt may identify the message by either id,
 * and the controller looks it up by whichever is present.
 */
final readonly class DeliveryUpdate
{
    /**
     * @param  'queued'|'sent'|'delivered'|'failed'  $status
     */
    public function __construct(
        public string $status,
        public ?string $providerMessageId = null,
        public ?string $wctpMessageId = null,
        public ?string $error = null,
    ) {}
}
