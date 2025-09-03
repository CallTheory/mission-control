<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DataSource;
use Twilio\Rest\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class TwilioService
{
    protected ?Client $client = null;
    protected ?string $fromNumber = null;
    protected ?DataSource $dataSource = null;

    protected function initialize(): void
    {
        if ($this->client !== null) {
            return; // Already initialized
        }

        $this->dataSource = DataSource::first();
        
        if (!$this->dataSource) {
            throw new Exception('No DataSource configured. Please configure Twilio credentials in System > Data Sources.');
        }
        
        // Get credentials from DataSource only
        $accountSid = $this->dataSource->twilio_account_sid 
            ? decrypt($this->dataSource->twilio_account_sid)
            : null;
            
        $authToken = $this->dataSource->twilio_auth_token
            ? decrypt($this->dataSource->twilio_auth_token)
            : null;
            
        $this->fromNumber = $this->dataSource->twilio_from_number;

        if (!$accountSid || !$authToken || !$this->fromNumber) {
            throw new Exception('Twilio credentials not configured in DataSource. Please configure in System > Data Sources.');
        }

        $this->client = new Client($accountSid, $authToken);
    }

    public function sendSms(string $to, string $message, array $options = []): array
    {
        $this->initialize();
        
        try {
            // Clean and format the phone number
            $to = $this->formatPhoneNumber($to);
            
            $messageOptions = [
                'from' => $options['from'] ?? $this->fromNumber,
                'body' => $message
            ];

            // Add optional parameters
            if (isset($options['statusCallback'])) {
                $messageOptions['statusCallback'] = $options['statusCallback'];
            }

            if (isset($options['mediaUrl'])) {
                $messageOptions['mediaUrl'] = $options['mediaUrl'];
            }

            $message = $this->client->messages->create($to, $messageOptions);

            return [
                'success' => true,
                'message_sid' => $message->sid,
                'to' => $message->to,
                'from' => $message->from,
                'status' => $message->status,
                'date_sent' => $message->dateSent,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage
            ];
        } catch (Exception $e) {
            Log::error('Twilio SMS send failed', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Add country code if not present (assuming US)
        if (strlen($phoneNumber) === 10) {
            $phoneNumber = '1' . $phoneNumber;
        }
        
        // Add + prefix for international format
        if (!str_starts_with($phoneNumber, '+')) {
            $phoneNumber = '+' . $phoneNumber;
        }
        
        return $phoneNumber;
    }

    public function getMessageStatus(string $messageSid): array
    {
        $this->initialize();
        
        try {
            $message = $this->client->messages($messageSid)->fetch();
            
            return [
                'success' => true,
                'status' => $message->status,
                'error_code' => $message->errorCode,
                'error_message' => $message->errorMessage,
                'date_sent' => $message->dateSent,
                'date_updated' => $message->dateUpdated
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function getFromNumber(): ?string
    {
        if (!$this->fromNumber && $this->dataSource) {
            $this->fromNumber = $this->dataSource->twilio_from_number;
        }
        
        return $this->fromNumber;
    }
}