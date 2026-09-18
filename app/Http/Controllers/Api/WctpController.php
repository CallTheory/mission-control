<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\SmsProvider;
use App\Http\Controllers\Controller;
use App\Jobs\ForwardToEnterpriseHost;
use App\Jobs\ProcessWctpMessage;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use App\Services\Sms\DeliveryUpdate;
use App\Services\Sms\InboundMessage;
use App\Services\Sms\SmsGateway;
use App\Services\Sms\SmsGatewayManager;
use App\Services\WctpService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WctpController extends Controller
{
    /**
     * Standard WCTP error codes.
     */
    const WCTP_ERROR_CODES = [
        '200' => 'Success',
        '300' => 'Other error',
        '301' => 'Invalid or missing XML syntax',
        '302' => 'Invalid or missing WCTP DTD version',
        '400' => 'Bad request',
        '401' => 'Unauthorized - invalid senderID',
        '402' => 'Unauthorized - invalid securityCode',
        '403' => 'Forbidden - invalid recipientID',
        '404' => 'Not found',
        '411' => 'Message content required',
        '500' => 'Internal server error',
        '503' => 'Service unavailable',
        '604' => 'Message too long',
        '606' => 'MCR (Message Control Record) error',
    ];

    protected WctpService $wctpService;

    protected SmsGatewayManager $gateways;

    public function __construct(WctpService $wctpService, SmsGatewayManager $gateways)
    {
        $this->wctpService = $wctpService;
        $this->gateways = $gateways;
    }

    /**
     * Handle incoming WCTP requests
     */
    public function handle(Request $request): Response
    {
        try {
            $xmlContent = $request->getContent();

            if (empty($xmlContent)) {
                return $this->errorResponse('301', 'No WCTP message provided');
            }

            Log::info('WCTP Request received', ['content_length' => strlen($xmlContent)]);

            // Parse the WCTP message
            $wctpData = $this->wctpService->parseWctpMessage($xmlContent);

            // Handle different operation types
            return match ($wctpData['operation']) {
                'wctp-SubmitRequest' => $this->handleSubmitRequest($wctpData['data']),
                'wctp-SubmitClientMessage' => $this->handleSubmitClientMessage($wctpData['data']),
                'wctp-ClientQuery' => $this->handleClientQuery($wctpData['data']),
                'wctp-MessageReply' => $this->handleMessageReply($wctpData['data']),
                default => $this->errorResponse('300', 'Unsupported operation: '.$wctpData['operation'])
            };

        } catch (Exception $e) {
            Log::error('WCTP processing error', ['error' => $e->getMessage()]);

            if (str_contains($e->getMessage(), 'XML parsing warnings') ||
                str_contains($e->getMessage(), 'String could not be parsed as XML')) {
                return $this->errorResponse('301', 'Invalid XML syntax');
            }

            if (str_contains($e->getMessage(), 'No valid WCTP operation found') ||
                str_contains($e->getMessage(), 'Unsupported WCTP operation')) {
                return $this->errorResponse('302', $e->getMessage());
            }

            return $this->errorResponse('300', 'Internal server error');
        }
    }

    /**
     * Handle WCTP SubmitRequest (outbound SMS)
     */
    protected function handleSubmitRequest(array $data): Response
    {
        // Extract message details
        $recipientPhone = preg_replace('/\D+/', '', $data['recipient_id']);
        $senderId = $data['sender_id'];
        $securityCode = $data['security_code'] ?? '';
        $message = $data['message'] ?? '';
        $messageId = $data['message_id'];

        // Validate required fields
        if (empty($recipientPhone) || strlen($recipientPhone) < 10) {
            return $this->errorResponse('403', 'Invalid recipientID');
        }

        if (empty($message)) {
            return $this->errorResponse('411', 'Message is required');
        }

        if (empty($senderId)) {
            return $this->errorResponse('401', 'Invalid senderID');
        }

        // Find and authenticate the Enterprise Host
        $host = EnterpriseHost::enabled()
            ->bySenderID($senderId)
            ->first();

        if (! $host) {
            return $this->errorResponse('401', 'sender not found');
        }

        if (! $host->validateSecurityCode($securityCode)) {
            return $this->errorResponse('402', 'Invalid securityCode');
        }

        // Get the from number for this Enterprise Host
        $fromNumber = $host->getOutboundPhoneNumber();
        if (! $fromNumber) {
            return $this->errorResponse('503', 'Service unavailable');
        }

        // Which carrier owns the sending number decides which gateway carries the
        // message; a number with no carrier assigned uses the system default.
        $provider = $this->providerForOutbound($host, $fromNumber);
        if ($provider === null) {
            return $this->errorResponse('503', 'Service unavailable');
        }

        // Extract reply code if present
        $replyWith = null;
        if (preg_match('/reply with (\d+)/i', $message, $matches)) {
            $replyWith = $matches[1];
        }

        // Create message record
        $wctpMessage = WctpMessage::create([
            'enterprise_host_id' => $host->id,
            'to' => $recipientPhone,
            'from' => $fromNumber,
            'message' => $message,
            'wctp_message_id' => $messageId,
            'provider' => $provider->value,
            'direction' => 'outbound',
            'status' => 'queued',
            'submitted_at' => now(),
            'reply_with' => $replyWith,
        ]);

        // Update host statistics
        $host->recordMessage();

        // Queue the message for processing
        ProcessWctpMessage::dispatch($wctpMessage);

        Log::info('WCTP message queued', [
            'wctp_message_id' => $messageId,
            'host' => $host->name,
            'to' => $recipientPhone,
            'provider' => $provider->value,
        ]);

        // Return success confirmation
        return response($this->wctpService->createConfirmation($messageId), 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * Handle WCTP SubmitClientMessage (transient client)
     */
    protected function handleSubmitClientMessage(array $data): Response
    {
        $recipientPhone = preg_replace('/\D+/', '', $data['recipient_id'] ?? '');
        $senderId = $data['sender_id'];
        $securityCode = $data['security_code'] ?? '';
        $message = $data['message'] ?? '';
        $messageId = $data['message_id'];

        if (empty($recipientPhone) || strlen($recipientPhone) < 10) {
            return $this->errorResponse('403', 'Invalid recipientID');
        }

        if (empty($message)) {
            return $this->errorResponse('411', 'Message is required');
        }

        if (empty($senderId)) {
            return $this->errorResponse('401', 'Invalid senderID');
        }

        // Find and authenticate the Enterprise Host
        $host = EnterpriseHost::enabled()
            ->bySenderID($senderId)
            ->first();

        if (! $host) {
            return $this->errorResponse('401', 'sender not found');
        }

        // For transient clients, miscInfo contains the security code
        if (! $host->validateSecurityCode($securityCode)) {
            return $this->errorResponse('402', 'Invalid securityCode');
        }

        $fromNumber = $host->getOutboundPhoneNumber();
        if (! $fromNumber) {
            return $this->errorResponse('503', 'Service unavailable');
        }

        $provider = $this->providerForOutbound($host, $fromNumber);
        if ($provider === null) {
            return $this->errorResponse('503', 'Service unavailable');
        }

        $wctpMessage = WctpMessage::create([
            'enterprise_host_id' => $host->id,
            'to' => $recipientPhone,
            'from' => $fromNumber,
            'message' => $message,
            'wctp_message_id' => $messageId,
            'provider' => $provider->value,
            'direction' => 'outbound',
            'status' => 'queued',
            'submitted_at' => now(),
        ]);

        $host->recordMessage();

        ProcessWctpMessage::dispatch($wctpMessage);

        Log::info('WCTP client message queued', [
            'wctp_message_id' => $messageId,
            'host' => $host->name,
            'to' => $recipientPhone,
            'provider' => $provider->value,
        ]);

        return response($this->wctpService->createConfirmation($messageId), 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * Handle WCTP ClientQuery (status check)
     */
    protected function handleClientQuery(array $data): Response
    {
        $senderId = $data['sender_id'];
        $securityCode = $data['security_code'] ?? '';
        $trackingNumber = $data['tracking_number'] ?? '';

        // Validate tracking number
        if (empty($trackingNumber)) {
            return $this->errorResponse('400', 'Tracking number is required');
        }

        // If sender is provided, authenticate
        $host = null;
        if (! empty($senderId)) {
            // Find and authenticate the Enterprise Host
            $host = EnterpriseHost::enabled()
                ->bySenderID($senderId)
                ->first();

            if (! $host) {
                return $this->errorResponse('401', 'sender not found');
            }

            if (! $host->validateSecurityCode($securityCode)) {
                return $this->errorResponse('401', 'Authentication failed');
            }
        }

        // Find the message by tracking number
        $query = WctpMessage::where('wctp_message_id', $trackingNumber);
        if ($host) {
            $query->where('enterprise_host_id', $host->id);
        }
        $message = $query->first();

        if (! $message) {
            // Return 404 status in a success response
            return response($this->wctpService->createStatusInfo($trackingNumber, '404', 'Message not found'), 200)
                ->header('Content-Type', 'text/xml; charset=UTF-8');
        }

        // Check for a status cached by a carrier delivery receipt
        $cachedStatus = cache()->get('wctp_status_'.$trackingNumber);
        if ($cachedStatus) {
            $message->status = $cachedStatus;
            $message->save();
        }

        // Map status to WCTP codes
        $statusCode = match ($message->status) {
            'delivered' => '200',
            'sent' => '201',
            'queued' => '202',
            'pending' => '202',
            'failed' => '400',
            default => '202'
        };

        // Return status info
        return response($this->wctpService->createStatusInfo($trackingNumber, $statusCode, $message->status), 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * Handle WCTP MessageReply
     */
    protected function handleMessageReply(array $data): Response
    {
        $responseToMessageId = $data['response_to_message_id'];
        $responseText = $data['response_text'];

        // Find the original message
        $originalMessage = WctpMessage::where('wctp_message_id', $responseToMessageId)->first();

        if (! $originalMessage) {
            return $this->errorResponse('404', 'Original message not found');
        }

        // Authenticate the replying host with its security code, exactly as every
        // other WCTP operation does. Without this, anyone who guesses a
        // wctp_message_id could inject a forged inbound reply to the host.
        $host = $originalMessage->enterpriseHost;

        if (! $host || ! $host->enabled) {
            return $this->errorResponse('401', 'sender not found');
        }

        if (! $host->validateSecurityCode($data['security_code'] ?? '')) {
            return $this->errorResponse('402', 'Invalid securityCode');
        }

        // Create a reply message
        $replyMessage = WctpMessage::create([
            'enterprise_host_id' => $originalMessage->enterprise_host_id,
            'to' => $originalMessage->from,
            'from' => $originalMessage->to,
            'message' => $responseText,
            'wctp_message_id' => uniqid('reply_'),
            'direction' => 'inbound',
            'status' => 'delivered',
            'parent_message_id' => $originalMessage->id,
        ]);

        // Forward the reply to the Enterprise Host asynchronously
        if ($host->callback_url) {
            $wctpXml = $this->wctpService->createInboundMessage(
                $replyMessage->from,
                $replyMessage->to,
                $responseText,
                $replyMessage->wctp_message_id
            );
            ForwardToEnterpriseHost::dispatch($host, $replyMessage, $wctpXml);
        }

        return response($this->wctpService->createConfirmation($replyMessage->wctp_message_id), 200)
            ->header('Content-Type', 'text/xml; charset=UTF-8');
    }

    /**
     * Handle incoming SMS on Twilio's original webhook path.
     *
     * Kept as its own entry point because that path is configured in Twilio
     * consoles that predate the other carriers; the work is identical.
     */
    public function handleIncomingSms(Request $request): Response
    {
        return $this->handleProviderWebhook($request, SmsProvider::Twilio->value);
    }

    /**
     * Handle Twilio status callbacks for outbound messages, on the original
     * per-message callback path. Answers 204, as it always has.
     */
    public function twilioCallback(Request $request, string $messageId): Response
    {
        return $this->processCarrierWebhook(
            $this->gateways->gateway(SmsProvider::Twilio),
            $request,
            $messageId,
        )
            ? response('', 204)
            : response('Webhook processing failed', 500);
    }

    /**
     * Handle a webhook from any carrier: inbound messages, delivery receipts, or
     * both in one request.
     *
     * Both kinds are processed on every carrier path because Bandwidth posts both to
     * the single callback URL configured on its messaging application. A carrier
     * that sent only one kind simply yields nothing for the other.
     */
    public function handleProviderWebhook(Request $request, string $provider, ?string $messageId = null): Response
    {
        $gateway = $this->gateways->gateway($provider);

        // A 5xx is what makes a carrier retry, so it is reserved for failures that a
        // retry could actually fix. An SMS to a number no enterprise host claims is
        // acknowledged: replaying it would not find a host either.
        return $this->processCarrierWebhook($gateway, $request, $messageId)
            ? $gateway->acknowledge()
            : response('Webhook processing failed', 500);
    }

    /**
     * @return bool false when the request could not be processed and the carrier
     *              should be asked to retry
     */
    protected function processCarrierWebhook(SmsGateway $gateway, Request $request, ?string $messageId): bool
    {
        try {
            foreach ($gateway->inboundMessages($request) as $inbound) {
                $this->receiveInboundMessage($gateway, $inbound);
            }

            foreach ($gateway->deliveryUpdates($request) as $update) {
                $this->applyDeliveryUpdate($gateway, $update, $messageId);
            }

            return true;
        } catch (Exception $e) {
            Log::error('Error handling carrier webhook', [
                'provider' => $gateway->provider()->value,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Route one inbound SMS to the enterprise host that owns the receiving number,
     * and forward it as WCTP if that host has a callback URL.
     */
    protected function receiveInboundMessage(SmsGateway $gateway, InboundMessage $inbound): void
    {
        $provider = $gateway->provider();

        Log::info('Incoming SMS received', [
            'provider' => $provider->value,
            'from' => $inbound->from,
            'to' => $inbound->to,
            'sid' => $inbound->providerMessageId,
        ]);

        $enterpriseHost = EnterpriseHost::findByPhoneNumber($inbound->to);

        if (! $enterpriseHost) {
            Log::warning('Incoming SMS to unassigned number', [
                'to' => $inbound->to,
                'provider' => $provider->value,
            ]);

            return;
        }

        $wctpMessage = WctpMessage::create([
            'enterprise_host_id' => $enterpriseHost->id,
            'to' => $inbound->to,
            'from' => $inbound->from,
            'message' => $inbound->body,
            'wctp_message_id' => $inbound->providerMessageId,
            // twilio_sid stays a Twilio SID; every carrier's id goes in
            // provider_message_id. See WctpMessage::markAsSent().
            'twilio_sid' => $provider === SmsProvider::Twilio ? $inbound->providerMessageId : null,
            'provider' => $provider->value,
            'provider_message_id' => $inbound->providerMessageId,
            'direction' => 'inbound',
            'status' => 'delivered',
            'delivered_at' => now(),
            'submitted_at' => now(),
        ]);

        // Update host statistics
        $enterpriseHost->recordMessage();

        // If there's a callback URL, forward the message asynchronously
        if ($enterpriseHost->callback_url) {
            $wctpXml = $this->wctpService->createInboundMessage(
                $inbound->from,
                $inbound->to,
                $inbound->body,
                $inbound->providerMessageId,
            );

            ForwardToEnterpriseHost::dispatch($enterpriseHost, $wctpMessage, $wctpXml);
        }
    }

    /**
     * Apply one delivery receipt to the message it belongs to.
     *
     * How the message is identified depends on the carrier: Twilio calls a URL that
     * carries our WCTP message id, Bandwidth echoes it back as the message tag, and
     * Commio only knows its own guid -- so the id from the route is preferred, then
     * the one in the payload, then the carrier's id.
     */
    protected function applyDeliveryUpdate(SmsGateway $gateway, DeliveryUpdate $update, ?string $messageIdFromRoute): void
    {
        $wctpMessageId = $messageIdFromRoute ?? $update->wctpMessageId;

        Log::info('Carrier status callback', [
            'provider' => $gateway->provider()->value,
            'wctp_message_id' => $wctpMessageId,
            'status' => $update->status,
            'sid' => $update->providerMessageId,
        ]);

        $wctpMessage = $this->findMessageForUpdate($wctpMessageId, $update->providerMessageId);

        // Cached for wctp-ClientQuery, which can ask for a status at any time. The
        // cached value is a status this application uses, not the carrier's own
        // wording, because ClientQuery writes it straight onto the message.
        foreach (array_unique(array_filter([$wctpMessageId, $wctpMessage?->wctp_message_id])) as $key) {
            cache()->put('wctp_status_'.$key, $update->status, now()->addMinutes(60));
        }

        if (! $wctpMessage) {
            Log::warning('Carrier status callback for an unknown message', [
                'provider' => $gateway->provider()->value,
                'wctp_message_id' => $wctpMessageId,
                'sid' => $update->providerMessageId,
            ]);

            return;
        }

        match ($update->status) {
            'delivered' => $wctpMessage->markAsDelivered(),
            'failed' => $wctpMessage->markAsFailed($update->error ?? 'Delivery failed'),
            // Never walk a status backwards: a receipt can arrive out of order.
            'queued' => $wctpMessage->status === 'pending' ? $wctpMessage->markAsQueued() : null,
            'sent' => $wctpMessage->status !== 'delivered' ? $wctpMessage->update(['status' => 'sent']) : null,
            default => null,
        };
    }

    /**
     * The message a delivery receipt refers to, by our id or the carrier's.
     */
    protected function findMessageForUpdate(?string $wctpMessageId, ?string $providerMessageId): ?WctpMessage
    {
        $candidates = [];

        if (filled($wctpMessageId)) {
            $candidates[] = ['wctp_message_id', $wctpMessageId];
        }

        if (filled($providerMessageId)) {
            $candidates[] = ['provider_message_id', $providerMessageId];
            // Twilio messages sent before provider_message_id existed only have this.
            $candidates[] = ['twilio_sid', $providerMessageId];
        }

        if ($candidates === []) {
            return null;
        }

        return WctpMessage::where(function ($query) use ($candidates) {
            foreach ($candidates as [$column, $value]) {
                $query->orWhere($column, $value);
            }
        })->first();
    }

    /**
     * The carrier that will carry an outbound message, or null when it cannot be
     * sent at all.
     *
     * The carrier owning the sending number wins, because a DID belongs to exactly
     * one carrier; numbers with none assigned use the system default.
     */
    protected function providerForOutbound(EnterpriseHost $host, string $fromNumber): ?SmsProvider
    {
        $provider = $host->providerForNumber($fromNumber) ?? $this->gateways->defaultProvider();

        if (! $this->gateways->gateway($provider)->isConfigured()) {
            Log::warning('WCTP submit rejected: carrier is not configured', [
                'host' => $host->name,
                'from' => $fromNumber,
                'provider' => $provider->value,
            ]);

            return null;
        }

        return $provider;
    }

    /**
     * Create WCTP error response
     */
    protected function errorResponse(string $errorCode, string $errorText): Response
    {
        $responseXml = $this->wctpService->createFailure($errorCode, $errorText);

        $httpCode = match ($errorCode) {
            '300' => 500,
            '301' => 400,
            '302' => 400,
            '400' => 400,
            '401', '402' => 401,
            '403' => 403,
            '404' => 404,
            '411' => 400,
            '500' => 500,
            '503' => 503,
            '604' => 400,
            '606' => 400,
            default => 500
        };

        return response($responseXml, $httpCode)
            ->header('Content-Type', 'text/xml');
    }
}
