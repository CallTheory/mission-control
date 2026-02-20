<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ForwardToEnterpriseHost;
use App\Jobs\ProcessWctpMessage;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
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

    public function __construct(WctpService $wctpService)
    {
        $this->wctpService = $wctpService;
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

        $wctpMessage = WctpMessage::create([
            'enterprise_host_id' => $host->id,
            'to' => $recipientPhone,
            'from' => $fromNumber,
            'message' => $message,
            'wctp_message_id' => $messageId,
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

        // Check for cached status from Twilio callback
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
        $host = $originalMessage->enterpriseHost;
        if ($host && $host->callback_url) {
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
     * Handle incoming SMS from Twilio (inbound SMS from phone to Enterprise Host)
     */
    public function handleIncomingSms(Request $request): Response
    {
        try {
            $from = $request->input('From');
            $to = $request->input('To');
            $body = $request->input('Body');
            $messageSid = $request->input('MessageSid');

            Log::info('Incoming SMS from Twilio', [
                'from' => $from,
                'to' => $to,
                'sid' => $messageSid,
            ]);

            // Find which Enterprise Host this message is for based on the receiving number
            $enterpriseHost = EnterpriseHost::findByPhoneNumber($to);

            if (! $enterpriseHost) {
                Log::warning('Incoming SMS to unassigned number', ['to' => $to]);

                // Return empty TwiML response to acknowledge receipt
                return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                    ->header('Content-Type', 'text/xml');
            }

            // Create inbound message record
            $wctpMessage = WctpMessage::create([
                'enterprise_host_id' => $enterpriseHost->id,
                'to' => $to,
                'from' => $from,
                'message' => $body,
                'wctp_message_id' => $messageSid,
                'twilio_sid' => $messageSid,
                'direction' => 'inbound',
                'status' => 'delivered',
                'delivered_at' => now(),
                'submitted_at' => now(),
            ]);

            // Update host statistics
            $enterpriseHost->recordMessage();

            // If there's a callback URL, forward the message asynchronously
            if ($enterpriseHost->callback_url) {
                $wctpXml = $this->wctpService->createInboundMessage($from, $to, $body, $messageSid);
                ForwardToEnterpriseHost::dispatch($enterpriseHost, $wctpMessage, $wctpXml);
            }

            // Return empty TwiML response
            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 200)
                ->header('Content-Type', 'text/xml');

        } catch (Exception $e) {
            Log::error('Error handling incoming SMS', ['error' => $e->getMessage()]);

            return response('<?xml version="1.0" encoding="UTF-8"?><Response></Response>', 500)
                ->header('Content-Type', 'text/xml');
        }
    }

    /**
     * Handle Twilio status callbacks for outbound messages
     */
    public function twilioCallback(Request $request, string $messageId): Response
    {
        $messageStatus = $request->input('MessageStatus');
        $messageSid = $request->input('MessageSid');
        $errorCode = $request->input('ErrorCode');

        Log::info('Twilio status callback', [
            'wctp_message_id' => $messageId,
            'status' => $messageStatus,
            'sid' => $messageSid,
        ]);

        // Cache the status for ClientQuery requests
        cache()->put('wctp_status_'.$messageId, $messageStatus, now()->addMinutes(60));

        // Find and update the message
        $wctpMessage = WctpMessage::where('wctp_message_id', $messageId)
            ->orWhere('twilio_sid', $messageSid)
            ->first();

        if ($wctpMessage) {
            switch ($messageStatus) {
                case 'delivered':
                    $wctpMessage->markAsDelivered();
                    break;
                case 'failed':
                case 'undelivered':
                    $errorMessage = $errorCode ? "Error {$errorCode}" : 'Delivery failed';
                    $wctpMessage->markAsFailed($errorMessage);
                    break;
                case 'queued':
                    if ($wctpMessage->status === 'pending') {
                        $wctpMessage->markAsQueued();
                    }
                    break;
                case 'sent':
                    if ($wctpMessage->status !== 'delivered') {
                        $wctpMessage->update(['status' => 'sent']);
                    }
                    break;
            }
        }

        return response('', 204);
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
