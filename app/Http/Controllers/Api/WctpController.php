<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWctpMessage;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use App\Services\TwilioService;
use App\Services\WctpService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WctpController extends Controller
{
    protected WctpService $wctpService;
    protected TwilioService $twilioService;

    public function __construct(WctpService $wctpService, TwilioService $twilioService)
    {
        $this->wctpService = $wctpService;
        $this->twilioService = $twilioService;
    }

    /**
     * Handle incoming WCTP requests
     */
    public function handle(Request $request): Response
    {
        try {
            $xmlContent = $request->getContent();

            if (empty($xmlContent)) {
                return $this->errorResponse('400', 'No WCTP message provided');
            }

            Log::info('WCTP Request received', ['content_length' => strlen($xmlContent)]);

            // Parse the WCTP message
            $wctpData = $this->wctpService->parseWctpMessage($xmlContent);
            
            // Handle different operation types
            return match ($wctpData['operation']) {
                'wctp-SubmitRequest' => $this->handleSubmitRequest($wctpData['data']),
                'wctp-ClientQuery' => $this->handleClientQuery($wctpData['data']),
                'wctp-MessageReply' => $this->handleMessageReply($wctpData['data']),
                default => $this->errorResponse('500', 'Unsupported operation: ' . $wctpData['operation'])
            };

        } catch (Exception $e) {
            Log::error('WCTP processing error', ['error' => $e->getMessage()]);
            
            // Return specific error for known WCTP parsing issues
            if (str_contains($e->getMessage(), 'No valid WCTP operation found') || 
                str_contains($e->getMessage(), 'Unsupported WCTP operation')) {
                return $this->errorResponse('500', $e->getMessage());
            }
            
            return $this->errorResponse('500', 'Internal server error');
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

        if (!$host) {
            return $this->errorResponse('401', 'sender not found');
        }

        if (!$host->validateSecurityCode($securityCode)) {
            return $this->errorResponse('402', 'Invalid securityCode');
        }

        // Get the from number for this Enterprise Host
        $fromNumber = $host->getOutboundPhoneNumber();
        if (!$fromNumber) {
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
            'status' => 'pending',
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
        if (!empty($senderId)) {
            // Find and authenticate the Enterprise Host
            $host = EnterpriseHost::enabled()
                ->bySenderID($senderId)
                ->first();

            if (!$host) {
                return $this->errorResponse('401', 'sender not found');
            }

            if (!$host->validateSecurityCode($securityCode)) {
                return $this->errorResponse('401', 'Authentication failed');
            }
        }

        // Find the message by tracking number
        $query = WctpMessage::where('wctp_message_id', $trackingNumber);
        if ($host) {
            $query->where('enterprise_host_id', $host->id);
        }
        $message = $query->first();

        if (!$message) {
            // Return 404 status in a success response
            return response($this->wctpService->createStatusInfo($trackingNumber, '404', 'Message not found'), 200)
                ->header('Content-Type', 'text/xml; charset=UTF-8');
        }

        // Check for cached status from Twilio callback
        $cachedStatus = cache()->get('wctp_status_' . $trackingNumber);
        if ($cachedStatus) {
            $message->status = $cachedStatus;
            $message->save();
        }

        // Map status to WCTP codes
        $statusCode = match ($message->status) {
            'delivered' => '200',
            'sent' => '201',
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
        
        if (!$originalMessage) {
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

        // Forward the reply to the Enterprise Host if they have a callback URL
        $host = $originalMessage->enterpriseHost;
        if ($host && $host->callback_url) {
            $this->forwardToEnterpriseHost($host, $replyMessage);
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
                'body' => $body,
                'sid' => $messageSid,
            ]);

            // Find which Enterprise Host this message is for based on the receiving number
            $enterpriseHost = EnterpriseHost::findByPhoneNumber($to);

            if (!$enterpriseHost) {
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
            ]);

            // Update host statistics
            $enterpriseHost->recordMessage();

            // If there's a callback URL, forward the message
            if ($enterpriseHost->callback_url) {
                $this->forwardInboundMessage($enterpriseHost, $from, $to, $body, $messageSid);
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
        cache()->put('wctp_status_' . $messageId, $messageStatus, now()->addMinutes(60));

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
                case 'sent':
                    if ($wctpMessage->status === 'pending') {
                        $wctpMessage->update(['status' => 'sent']);
                    }
                    break;
            }
        }

        return response('', 204);
    }

    /**
     * Forward inbound SMS to Enterprise Host callback URL
     */
    protected function forwardInboundMessage(EnterpriseHost $host, string $from, string $to, string $body, string $twilioSid): void
    {
        try {
            $wctpXml = $this->wctpService->createInboundMessage($from, $to, $body, $twilioSid);

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'text/xml'])
                ->post($host->callback_url, $wctpXml);

            Log::info('Inbound message forwarded to Enterprise Host', [
                'host' => $host->name,
                'callback_url' => $host->callback_url,
                'status' => $response->status(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to forward inbound message', [
                'host' => $host->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Forward message to Enterprise Host callback URL
     */
    protected function forwardToEnterpriseHost(EnterpriseHost $host, WctpMessage $message): void
    {
        try {
            $wctpXml = $this->wctpService->createInboundMessage(
                $message->from,
                $message->to,
                $message->message,
                $message->wctp_message_id
            );

            $response = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'text/xml'])
                ->post($host->callback_url, $wctpXml);

            Log::info('Message forwarded to Enterprise Host', [
                'host' => $host->name,
                'callback_url' => $host->callback_url,
                'message_id' => $message->wctp_message_id,
                'status' => $response->status(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to forward message', [
                'host' => $host->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create WCTP error response
     */
    protected function errorResponse(string $errorCode, string $errorText): Response
    {
        $responseXml = $this->wctpService->createFailure($errorCode, $errorText);

        $httpCode = match ($errorCode) {
            '400' => 400,
            '401', '402' => 401,
            '403' => 403,
            '411' => 500,
            '404' => 404,
            '500' => 500,
            '503' => 503,
            default => 500
        };

        return response($responseXml, $httpCode)
            ->header('Content-Type', 'text/xml');
    }
}