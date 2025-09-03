<?php

declare(strict_types=1);

namespace Tests\Mocks;

use App\Services\TwilioService;

class MockTwilioService extends TwilioService
{
    public static bool $shouldFail = false;
    public static array $sentMessages = [];
    public static ?string $lastMessageSid = null;

    public function __construct()
    {
        // Don't call parent constructor to avoid initializing real Twilio client
    }

    public function sendSms(string $to, string $message, array $options = []): array
    {
        // Reset for each test
        if (empty(self::$sentMessages)) {
            self::$shouldFail = false;
        }

        // Store the message for assertions
        $messageData = [
            'to' => $to,
            'from' => $options['from'] ?? '+15551234567',
            'message' => $message,
            'options' => $options,
        ];
        self::$sentMessages[] = $messageData;

        if (self::$shouldFail) {
            return [
                'success' => false,
                'error' => 'Mock Twilio error: Service unavailable'
            ];
        }

        $sid = 'SM' . substr(md5($to . $message . microtime()), 0, 30);
        self::$lastMessageSid = $sid;

        return [
            'success' => true,
            'message_sid' => $sid,
            'to' => $to,
            'from' => $messageData['from'],
            'status' => 'queued',
            'date_sent' => now()->toIso8601String(),
            'error_code' => null,
            'error_message' => null
        ];
    }

    public function getMessageStatus(string $messageSid): array
    {
        // Mock implementation
        return [
            'success' => true,
            'status' => 'delivered',
            'error_code' => null,
            'error_message' => null,
            'date_sent' => now()->toIso8601String(),
            'date_updated' => now()->toIso8601String()
        ];
    }

    public function getFromNumber(): ?string
    {
        return '+15551234567';
    }

    public static function reset(): void
    {
        self::$shouldFail = false;
        self::$sentMessages = [];
        self::$lastMessageSid = null;
    }

    public static function assertMessageSent(string $to, ?string $messageContent = null): bool
    {
        foreach (self::$sentMessages as $message) {
            if ($message['to'] === $to) {
                if ($messageContent === null || str_contains($message['message'], $messageContent)) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function getLastMessage(): ?array
    {
        return empty(self::$sentMessages) ? null : end(self::$sentMessages);
    }
}