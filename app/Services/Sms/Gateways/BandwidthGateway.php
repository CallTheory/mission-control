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
 * Bandwidth, via the v2 Messaging API.
 *
 * Two things differ from Twilio and shape this class:
 *
 * 1. Sending is a plain REST call authenticated with the API token/secret pair as
 *    HTTP Basic, and every message must name a Bandwidth `applicationId`.
 * 2. There is no per-message status callback URL. Bandwidth posts BOTH inbound
 *    messages and delivery receipts to the one callback URL configured on the
 *    messaging application, as a JSON array of events. So receipts are correlated
 *    by the `tag` we send with each message -- our WCTP message id -- rather than
 *    by a URL that carries the id.
 *
 * @see https://dev.bandwidth.com/docs/messaging
 */
class BandwidthGateway extends Gateway
{
    use AuthenticatesCarrierWebhook;

    public function provider(): SmsProvider
    {
        return SmsProvider::Bandwidth;
    }

    public function isConfigured(): bool
    {
        return filled($this->setting('bandwidth_account_id'))
            && filled($this->setting('bandwidth_api_token'))
            && filled($this->setting('bandwidth_api_secret'))
            && filled($this->setting('bandwidth_application_id'))
            && filled($this->setting('bandwidth_from_number'));
    }

    public function fromNumber(): ?string
    {
        return $this->setting('bandwidth_from_number');
    }

    public function sendSms(string $to, string $message, array $options = []): array
    {
        try {
            if (! $this->isConfigured()) {
                throw new Exception('Bandwidth credentials are not configured. Configure them in System > Integrations.');
            }

            $accountId = (string) $this->setting('bandwidth_account_id');

            $payload = [
                'applicationId' => $this->setting('bandwidth_application_id'),
                'to' => [$this->e164($to)],
                'from' => $this->e164((string) ($options['from'] ?? $this->fromNumber())),
                'text' => $message,
            ];

            // The only handle Bandwidth gives us on a delivery receipt, since its
            // callback URL is per-application rather than per-message.
            if (filled($options['messageId'] ?? null)) {
                $payload['tag'] = (string) $options['messageId'];
            }

            $response = Http::withBasicAuth(
                (string) $this->setting('bandwidth_api_token'),
                (string) $this->setting('bandwidth_api_secret'),
            )
                ->timeout((int) config('services.bandwidth.timeout', 30))
                ->acceptJson()
                ->asJson()
                ->post(
                    rtrim((string) config('services.bandwidth.endpoint'), '/')."/api/v2/users/{$accountId}/messages",
                    $payload,
                );

            if ($response->failed()) {
                throw new Exception($this->errorFrom($response->json(), $response->status()));
            }

            $body = $response->json();

            return [
                'success' => true,
                'message_sid' => (string) ($body['id'] ?? ''),
                'to' => $payload['to'][0],
                'from' => $payload['from'],
                'status' => 'queued',
                'date_sent' => $body['time'] ?? null,
                'error_code' => null,
                'error_message' => null,
            ];
        } catch (Exception $e) {
            Log::error('Bandwidth SMS send failed', [
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
        $messages = [];

        foreach ($this->events($request) as $event) {
            if (($event['type'] ?? null) !== 'message-received') {
                continue;
            }

            $message = $event['message'] ?? [];

            // `to` is an array on the message (an MMS group can have several); the
            // event's own `to` is the one number this event is about.
            $to = $event['to'] ?? ($message['to'][0] ?? null);

            if (blank($message['from'] ?? null) || blank($to) || blank($message['id'] ?? null)) {
                continue;
            }

            $messages[] = new InboundMessage(
                from: (string) $message['from'],
                to: (string) $to,
                body: (string) ($message['text'] ?? ''),
                providerMessageId: (string) $message['id'],
            );
        }

        return $messages;
    }

    public function deliveryUpdates(Request $request): array
    {
        $updates = [];

        foreach ($this->events($request) as $event) {
            $status = match ($event['type'] ?? null) {
                'message-delivered' => 'delivered',
                'message-failed' => 'failed',
                'message-sending' => 'sent',
                default => null,
            };

            if ($status === null) {
                continue;
            }

            $message = $event['message'] ?? [];

            $updates[] = new DeliveryUpdate(
                status: $status,
                providerMessageId: isset($message['id']) ? (string) $message['id'] : null,
                // The tag is the WCTP message id we sent with the message.
                wctpMessageId: filled($message['tag'] ?? null) ? (string) $message['tag'] : null,
                error: $status === 'failed'
                    ? trim(($event['errorCode'] ?? '') !== ''
                        ? "Error {$event['errorCode']}: ".($event['description'] ?? '')
                        : ($event['description'] ?? 'Delivery failed'), ': ')
                    : null,
            );
        }

        return $updates;
    }

    public function acknowledge(): Response
    {
        // Bandwidth only needs a 2xx; anything else makes it retry the event.
        return response('', 200);
    }

    /**
     * Bandwidth posts a JSON array of events, but tolerate a bare object too --
     * single-event bodies show up in portal test tools.
     *
     * @return array<int, array<string, mixed>>
     */
    private function events(Request $request): array
    {
        $payload = $request->json()->all();

        if ($payload === []) {
            return [];
        }

        // A single event object rather than a list.
        if (array_key_exists('type', $payload)) {
            return [$payload];
        }

        return array_values(array_filter($payload, 'is_array'));
    }

    private function errorFrom(mixed $body, int $status): string
    {
        if (is_array($body)) {
            $description = $body['description'] ?? $body['message'] ?? null;
            $type = $body['type'] ?? null;

            if (filled($description)) {
                return trim(($type ? "{$type}: " : '').$description);
            }
        }

        return "Bandwidth returned HTTP {$status}";
    }

    protected function callbackBasicCredentials(): array
    {
        return [
            $this->setting('bandwidth_callback_username'),
            $this->setting('bandwidth_callback_password'),
        ];
    }

    protected function callbackToken(): ?string
    {
        return $this->setting('bandwidth_callback_token');
    }
}
