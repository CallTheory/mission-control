<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use SimpleXMLElement;

class WctpService
{
    const WCTP_VERSION = '1.3';
    const DTD_URL = 'http://www.wctp.org/release/wctp-dtd-v1r3.dtd';

    /**
     * Parse incoming WCTP XML message
     */
    public function parseWctpMessage(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);
            $operation = $this->getOperation($xml);

            $data = match ($operation) {
                'wctp-SubmitRequest' => $this->parseSubmitRequest($xml),
                'wctp-ClientQuery' => $this->parseClientQuery($xml),
                'wctp-MessageReply' => $this->parseMessageReply($xml),
                default => throw new Exception("Unsupported WCTP operation: {$operation}")
            };

            return [
                'operation' => $operation,
                'data' => $data,
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to parse WCTP message: '.$e->getMessage());
        }
    }

    /**
     * Get the WCTP operation type from XML
     */
    protected function getOperation(SimpleXMLElement $xml): string
    {
        if (isset($xml->{'wctp-SubmitRequest'})) {
            return 'wctp-SubmitRequest';
        }
        if (isset($xml->{'wctp-ClientQuery'})) {
            return 'wctp-ClientQuery';
        }
        if (isset($xml->{'wctp-MessageReply'})) {
            return 'wctp-MessageReply';
        }
        
        throw new Exception('No valid WCTP operation found');
    }

    /**
     * Parse a WCTP SubmitRequest for SMS relay
     */
    protected function parseSubmitRequest(SimpleXMLElement $xml): array
    {
        $submitRequest = $xml->{'wctp-SubmitRequest'};
        $submitHeader = $submitRequest->{'wctp-SubmitHeader'};
        $payload = $submitRequest->{'wctp-Payload'};

        // Get originator (Enterprise Host credentials)
        $originator = $submitHeader->{'wctp-ClientOriginator'} ?? $submitHeader->{'wctp-Originator'} ?? null;

        // Extract message text and type - only if elements exist and have content
        $message = null;
        $messageType = null;
        $data = [];
        
        if (isset($payload->{'wctp-Alphanumeric'}) && (string) $payload->{'wctp-Alphanumeric'} !== '') {
            $message = (string) $payload->{'wctp-Alphanumeric'};
            $messageType = 'alphanumeric';
        } elseif (isset($payload->{'wctp-TransparentData'})) {
            $transparentData = $payload->{'wctp-TransparentData'};
            $dataContent = (string) ($transparentData['data'] ?? '');
            if ($dataContent !== '') {
                $message = $dataContent;
                $messageType = 'transparent';
                $data['encoding'] = (string) ($transparentData['encoding'] ?? 'base64');
            }
        } elseif (isset($payload->{'wctp-Message'}) && (string) $payload->{'wctp-Message'} !== '') {
            $message = (string) $payload->{'wctp-Message'};
            $messageType = 'message';
        }

        // Get message control attributes
        $messageControl = [];
        if (isset($submitHeader->{'wctp-MessageControl'})) {
            $control = $submitHeader->{'wctp-MessageControl'};
            $messageControl = [
                'message_id' => (string) ($control['messageID'] ?? uniqid('wctp_')),
                'transaction_id' => (string) ($control['transactionID'] ?? ''),
                'allow_response' => (string) ($control['allowResponse'] ?? 'false'),
                'notification_requested' => (string) ($control['notificationRequest'] ?? 'false'),
            ];
        }

        $result = [
            'sender_id' => $originator ? (string) ($originator['senderID'] ?? '') : '',
            'security_code' => $originator ? (string) ($originator['securityCode'] ?? '') : '',
            'recipient_id' => (string) ($submitHeader->{'wctp-Recipient'}['recipientID'] ?? ''),
            'message_id' => $messageControl['message_id'] ?? uniqid('wctp_'),
            'message_control' => $messageControl ?: ['message_id' => uniqid('wctp_')],
        ];
        
        // Only add message and message_type if we actually found content
        if ($message !== null) {
            $result['message'] = $message;
        }
        if ($messageType !== null) {
            $result['message_type'] = $messageType;
        }
        
        // Add encoding if present
        if (isset($data['encoding'])) {
            $result['encoding'] = $data['encoding'];
        }
        
        return $result;
    }

    /**
     * Create a WCTP success confirmation response
     */
    public function createConfirmation(string $messageId, string $successCode = '200', string $successText = 'Message accepted for delivery'): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE wctp-Operation SYSTEM "'.self::DTD_URL.'"><wctp-Operation></wctp-Operation>');
        $xml->addAttribute('wctpVersion', self::WCTP_VERSION);

        $confirmation = $xml->addChild('wctp-Confirmation');
        $success = $confirmation->addChild('wctp-Success');
        $success->addAttribute('successCode', $successCode);
        $success->addAttribute('successText', $successText);
        $success->addAttribute('messageID', $messageId);

        return $xml->asXML();
    }

    /**
     * Create a WCTP failure response
     */
    public function createFailure(string $errorCode, string $errorText): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE wctp-Operation SYSTEM "'.self::DTD_URL.'"><wctp-Operation></wctp-Operation>');
        $xml->addAttribute('wctpVersion', self::WCTP_VERSION);

        $confirmation = $xml->addChild('wctp-Confirmation');
        $failure = $confirmation->addChild('wctp-Failure');
        $failure->addAttribute('errorCode', $errorCode);
        $failure->addAttribute('errorText', $errorText);

        return $xml->asXML();
    }

    /**
     * Create a WCTP MessageReply for inbound SMS (from phone to Enterprise Host)
     */
    public function createInboundMessage(string $from, string $to, string $messageText, string $twilioSid): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE wctp-Operation SYSTEM "'.self::DTD_URL.'"><wctp-Operation></wctp-Operation>');
        $xml->addAttribute('wctpVersion', self::WCTP_VERSION);

        $submitRequest = $xml->addChild('wctp-SubmitRequest');
        
        // Header
        $header = $submitRequest->addChild('wctp-SubmitHeader');
        $header->addChild('wctp-Originator')->addAttribute('senderID', $from);
        $header->addChild('wctp-Recipient')->addAttribute('recipientID', $to);
        
        $messageControl = $header->addChild('wctp-MessageControl');
        $messageControl->addAttribute('messageID', $twilioSid);
        
        // Payload
        $payload = $submitRequest->addChild('wctp-Payload');
        $payload->addChild('wctp-Alphanumeric', htmlspecialchars($messageText));

        return $xml->asXML();
    }

    /**
     * Parse a WCTP ClientQuery request
     */
    protected function parseClientQuery(SimpleXMLElement $xml): array
    {
        $clientQuery = $xml->{'wctp-ClientQuery'};
        
        // Check both locations for the header
        $queryHeader = $clientQuery->{'wctp-ClientQueryHeader'} ?? $clientQuery;
        
        $originator = $queryHeader->{'wctp-ClientOriginator'} ?? null;
        
        return [
            'sender_id' => $originator ? (string) ($originator['senderID'] ?? '') : '',
            'security_code' => $originator ? (string) ($originator['securityCode'] ?? '') : '',
            'tracking_number' => (string) ($queryHeader->{'wctp-TrackingNumber'} ?? ''),
        ];
    }

    /**
     * Parse a WCTP MessageReply
     */
    protected function parseMessageReply(SimpleXMLElement $xml): array
    {
        $messageReply = $xml->{'wctp-MessageReply'};
        
        return [
            'response_to_message_id' => (string) $messageReply['responseToMessageID'] ?? '',
            'response_text' => (string) $messageReply['responseText'] ?? '',
            'submit_time_stamp' => (string) $messageReply['submitTimestamp'] ?? '',
        ];
    }

    /**
     * Create a WCTP StatusInfo response for ClientQuery
     */
    public function createStatusInfo(string $messageId, string $statusCode, string $statusText): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE wctp-Operation SYSTEM "'.self::DTD_URL.'"><wctp-Operation></wctp-Operation>');
        $xml->addAttribute('wctpVersion', self::WCTP_VERSION);

        $statusInfo = $xml->addChild('wctp-StatusInfo');
        $statusInfo->addAttribute('messageID', $messageId);
        
        // Add notification element for proper WCTP format
        $notification = $statusInfo->addChild('wctp-Notification');
        $notification->addAttribute('notificationCode', $statusCode);
        $notification->addAttribute('notificationText', $statusText);

        return $xml->asXML();
    }

    /**
     * Create a WCTP SubmitRequest (for outbound messages)
     */
    public function createSubmitRequest(string $from, string $to, string $message, ?string $messageId = null): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE wctp-Operation SYSTEM "'.self::DTD_URL.'"><wctp-Operation></wctp-Operation>');
        $xml->addAttribute('wctpVersion', self::WCTP_VERSION);

        $submitRequest = $xml->addChild('wctp-SubmitRequest');
        
        // Header
        $header = $submitRequest->addChild('wctp-SubmitHeader');
        $header->addChild('wctp-Originator')->addAttribute('senderID', $from);
        $header->addChild('wctp-Recipient')->addAttribute('recipientID', $to);
        
        $messageControl = $header->addChild('wctp-MessageControl');
        $messageControl->addAttribute('messageID', $messageId ?? uniqid('wctp_'));
        
        // Payload
        $payload = $submitRequest->addChild('wctp-Payload');
        $payload->addChild('wctp-Alphanumeric', htmlspecialchars($message));

        return $xml->asXML();
    }

    /**
     * Create a WCTP ClientQuery request
     */
    public function createClientQuery(string $senderId, string $securityCode, string $trackingNumber): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE wctp-Operation SYSTEM "'.self::DTD_URL.'"><wctp-Operation></wctp-Operation>');
        $xml->addAttribute('wctpVersion', self::WCTP_VERSION);

        $clientQuery = $xml->addChild('wctp-ClientQuery');
        $header = $clientQuery->addChild('wctp-ClientQueryHeader');
        
        $originator = $header->addChild('wctp-ClientOriginator');
        $originator->addAttribute('senderID', $senderId);
        $originator->addAttribute('securityCode', $securityCode);
        
        $header->addChild('wctp-TrackingNumber', $trackingNumber);

        return $xml->asXML();
    }
}