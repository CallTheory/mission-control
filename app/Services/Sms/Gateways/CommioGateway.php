<?php

declare(strict_types=1);

namespace App\Services\Sms\Gateways;

use App\Enums\SmsProvider;
use App\Services\Sms\Concerns\AuthenticatesCarrierWebhook;
use App\Services\Sms\DeliveryUpdate;
use App\Services\Sms\InboundMessage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Com.io (thinQ), via the origination SMS product API.
 *
 * Differences that shape this class:
 *
 * 1. The send endpoint is account-scoped -- POST
 *    `/account/{id}/product/origination/sms/send` -- authenticated with the portal
 *    username and an API token as HTTP Basic, and it takes bare digits
 *    (`15551234567`) rather than E.164.
 * 2. Like Bandwidth there is no per-message callback URL: the inbound and DLR URLs
 *    are configured once in the portal. Unlike Bandwidth there is no tag to carry
 *    our own id either, so receipts correlate on thinQ's `guid`, which is stored as
 *    the message's provider_message_id when we send.
 * 3. Field names vary between the portal's inbound and DLR posts (and between form
 *    and JSON bodies), so reads here accept every spelling thinQ has been observed
 *    to send rather than one canonical key.
 */
class CommioGateway extends Gateway
{
    use AuthenticatesCarrierWebhook;

    public function provider(): SmsProvider
    {
        return SmsProvider::Commio;
    }

    public function isConfigured(): bool
    {
        return filled($this->setting('commio_account_id'))
            && filled($this->setting('commio_username'))
            && filled($this->setting('commio_api_token'))
            && filled($this->setting('commio_from_number'));
    }

    public function fromNumber(): ?string
    {
        return $this->setting('commio_from_number');
    }

    public function sendSms(string $to, string $message, array $options = []): array
    {
        try {
            if (! $this->isConfigured()) {
                throw new Exception('Com.io credentials are not configured. Configure them in System > Integrations.');
            }

            $accountId = (string) $this->setting('commio_account_id');

            $payload = [
                'from_did' => $this->digits((string) ($options['from'] ?? $this->fromNumber())),
                'to_did' => $this->digits($to),
                'message' => $message,
            ];

            $response = Http::withBasicAuth(
                (string) $this->setting('commio_username'),
                (string) $this->setting('commio_api_token'),
            )
                ->timeout((int) config('services.commio.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post(
                    rtrim((string) config('services.commio.endpoint'), '/')
                        ."/account/{$accountId}/product/origination/sms/send",
                    $payload,
                );

            if ($response->failed()) {
                throw new Exception($this->errorFrom($response->json(), $response->body(), $response->status()));
            }

            $body = $response->json();
            $guid = is_array($body)
                ? (string) ($body['guid'] ?? $body['id'] ?? $body['sms_guid'] ?? '')
                : '';

            if ($guid === '') {
                throw new Exception('Com.io accepted the request but returned no message guid');
            }

            return [
                'success' => true,
                'message_sid' => $guid,
                'to' => $payload['to_did'],
                'from' => $payload['from_did'],
                'status' => 'queued',
                'date_sent' => is_array($body) ? ($body['created_at'] ?? null) : null,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Exception $e) {
            Log::error('Com.io SMS send failed', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function inboundMessages(Request $request): array
    {
        // A delivery receipt carries a status field and an inbound message does not;
        // that is what tells the two apart when they share an endpoint.
        if ($this->rawStatus($request) !== null) {
            return [];
        }

        $from = $this->first($request, ['from', 'from_did', 'From', 'source', 'src']);
        $to = $this->first($request, ['to', 'to_did', 'To', 'destination', 'dst']);
        $guid = $this->first($request, ['guid', 'id', 'sms_guid', 'message_id']);

        if (blank($from) || blank($to) || blank($guid)) {
            return [];
        }

        return [new InboundMessage(
            from: $this->e164($from),
            to: $this->e164($to),
            body: (string) ($this->first($request, ['message', 'text', 'body', 'Body']) ?? ''),
            providerMessageId: $guid,
        )];
    }

    public function deliveryUpdates(Request $request): array
    {
        $raw = $this->rawStatus($request);

        if ($raw === null) {
            return [];
        }

        $status = $this->normaliseStatus($raw);

        if ($status === null) {
            return [];
        }

        return [new DeliveryUpdate(
            status: $status,
            providerMessageId: $this->first($request, ['guid', 'id', 'sms_guid', 'message_id']),
            wctpMessageId: null,
            error: $status === 'failed'
                ? (string) ($this->first($request, ['error', 'error_message', 'reason', 'description']) ?? "Delivery failed ({$raw})")
                : null,
        )];
    }

    public function acknowledge(): Response
    {
        return response('OK', 200)->header('Content-Type', 'text/plain');
    }

    private function rawStatus(Request $request): ?string
    {
        $status = $this->first($request, ['status', 'state', 'delivery_status', 'dlr_status', 'message_status']);

        return $status === null ? null : strtolower($status);
    }

    /**
     * thinQ reports SMPP-flavoured states on some accounts and words on others.
     */
    private function normaliseStatus(string $status): ?string
    {
        return match ($status) {
            'delivered', 'delivrd', 'success', 'ok' => 'delivered',
            'failed', 'undeliv', 'undelivered', 'undeliverable', 'rejected', 'rejectd', 'expired', 'error' => 'failed',
            'sent', 'accepted', 'accepted_by_carrier' => 'sent',
            'queued', 'enroute', 'pending' => 'queued',
            default => null,
        };
    }

    /**
     * The first of several possible field spellings that is actually present.
     *
     * @param  array<int, string>  $keys
     */
    private function first(Request $request, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_scalar($value) && filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function errorFrom(mixed $json, string $raw, int $status): string
    {
        if (is_array($json)) {
            $message = $json['message'] ?? $json['error'] ?? $json['description'] ?? null;

            if (is_array($message)) {
                $message = implode('; ', array_map('strval', $message));
            }

            if (filled($message)) {
                return (string) $message;
            }
        }

        return filled($raw)
            ? "Com.io returned HTTP {$status}: ".mb_substr($raw, 0, 200)
            : "Com.io returned HTTP {$status}";
    }

    protected function callbackBasicCredentials(): array
    {
        return [
            $this->setting('commio_callback_username'),
            $this->setting('commio_callback_password'),
        ];
    }

    protected function callbackToken(): ?string
    {
        return $this->setting('commio_callback_token');
    }
}
