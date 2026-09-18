<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use App\Enums\SmsProvider;
use App\Services\Sms\DeliveryUpdate;
use App\Services\Sms\InboundMessage;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Twilio\Security\RequestValidator;

/**
 * Twilio, the carrier the gateway shipped with.
 *
 * Sending still goes through the existing TwilioService (resolved from the
 * container, so the test double keeps working); this class only adds the webhook
 * half of the SmsGateway contract around it.
 */
class TwilioGateway extends Gateway
{
    public function __construct(protected TwilioService $twilio) {}

    public function provider(): SmsProvider
    {
        return SmsProvider::Twilio;
    }

    public function isConfigured(): bool
    {
        return filled($this->setting('twilio_account_sid'))
            && filled($this->setting('twilio_auth_token'))
            && filled($this->setting('twilio_from_number'));
    }

    public function fromNumber(): ?string
    {
        return $this->setting('twilio_from_number');
    }

    public function sendSms(string $to, string $message, array $options = []): array
    {
        return $this->twilio->sendSms($to, $message, $options);
    }

    /**
     * Twilio signs its callbacks, so the signature is the credential -- there is
     * nothing extra to configure. Fails closed when no auth token is stored.
     */
    public function verifyWebhook(Request $request): bool
    {
        $authToken = $this->setting('twilio_auth_token');

        if (! $authToken) {
            Log::warning('Twilio request rejected: no auth token configured to validate signature');

            return false;
        }

        $signature = $request->header('X-Twilio-Signature', '');

        if (empty($signature)) {
            Log::warning('Twilio request rejected: missing X-Twilio-Signature header');

            return false;
        }

        if (! (new RequestValidator($authToken))->validate($signature, $request->fullUrl(), $request->all())) {
            Log::warning('Twilio request rejected: invalid signature', ['url' => $request->fullUrl()]);

            return false;
        }

        return true;
    }

    public function inboundMessages(Request $request): array
    {
        // A status callback carries From, To and MessageSid too, so what separates
        // the two is MessageStatus -- present on a receipt, absent on a message.
        if ($request->has('MessageStatus')) {
            return [];
        }

        $body = $request->input('Body');
        $from = $request->input('From');
        $to = $request->input('To');
        $sid = $request->input('MessageSid');

        if (blank($from) || blank($to) || blank($sid)) {
            return [];
        }

        return [new InboundMessage(
            from: (string) $from,
            to: (string) $to,
            body: (string) ($body ?? ''),
            providerMessageId: (string) $sid,
        )];
    }

    public function deliveryUpdates(Request $request): array
    {
        $status = $this->normaliseStatus((string) $request->input('MessageStatus', ''));

        if ($status === null) {
            return [];
        }

        $errorCode = $request->input('ErrorCode');

        return [new DeliveryUpdate(
            status: $status,
            providerMessageId: $request->input('MessageSid'),
            wctpMessageId: null,
            error: $status === 'failed'
                ? ($errorCode ? "Error {$errorCode}" : 'Delivery failed')
                : null,
        )];
    }

    public function acknowledge(): Response
    {
        return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
            ->header('Content-Type', 'text/xml');
    }

    private function normaliseStatus(string $status): ?string
    {
        return match ($status) {
            'delivered' => 'delivered',
            'failed', 'undelivered' => 'failed',
            'queued', 'accepted', 'scheduled' => 'queued',
            'sent' => 'sent',
            default => null,
        };
    }
}
