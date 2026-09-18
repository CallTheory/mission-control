<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\SmsProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * What the WCTP gateway needs from an SMS carrier.
 *
 * Three carriers are implemented (Twilio, Bandwidth, Com.io/thinQ) and they differ
 * in every direction: different REST endpoints and auth for sending, different
 * webhook payload shapes for receiving, different ways of authenticating their own
 * callbacks, and different acknowledgement bodies they expect back. This interface
 * is the seam -- WctpController and ProcessWctpMessage only ever talk to it, so
 * adding a fourth carrier is one class plus its credential columns.
 *
 * @see SmsGatewayManager for resolution and per-number routing
 */
interface SmsGateway
{
    public function provider(): SmsProvider;

    /**
     * Whether every credential needed to actually send is present.
     */
    public function isConfigured(): bool;

    /**
     * The system-wide number for this carrier, used when a host has no number of
     * its own assigned.
     */
    public function fromNumber(): ?string;

    /**
     * Send one message.
     *
     * Options: `from` (defaults to fromNumber()), `statusCallback` (where the
     * carrier should post delivery receipts) and `messageId` (our WCTP message id,
     * passed through as a carrier tag where the carrier supports one).
     *
     * Returns the same shape for every carrier:
     * `['success' => true, 'message_sid' => string, ...]` or
     * `['success' => false, 'error' => string]`. Callers must not throw on a failed
     * send -- ProcessWctpMessage turns the error into a retry.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendSms(string $to, string $message, array $options = []): array;

    /**
     * Whether an inbound webhook really came from this carrier. Implementations
     * MUST fail closed: no configured credential means no trusted request.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Inbound messages carried by this webhook request. Carriers that batch events
     * (Bandwidth posts a JSON array) may return several; a request carrying only
     * delivery receipts returns none.
     *
     * @return array<int, InboundMessage>
     */
    public function inboundMessages(Request $request): array;

    /**
     * Delivery receipts carried by this webhook request. Bandwidth posts these to
     * the same URL as inbound messages, which is why both live on one request.
     *
     * @return array<int, DeliveryUpdate>
     */
    public function deliveryUpdates(Request $request): array;

    /**
     * The body this carrier expects back from its webhook. Twilio wants TwiML;
     * the others just want a 2xx.
     */
    public function acknowledge(): Response;
}
