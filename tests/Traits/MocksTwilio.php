<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Services\TwilioService;
use Tests\Mocks\MockTwilioService;
use App\Models\DataSource;
use App\Models\EnterpriseHost;

trait MocksTwilio
{
    protected function setUpTwilioMock(): void
    {
        // Reset the mock
        MockTwilioService::reset();
        
        // Bind the mock to the service container
        $this->app->singleton(TwilioService::class, function () {
            return new MockTwilioService();
        });
        
        // Create a DataSource with Twilio credentials
        $this->createTwilioDataSource();
    }
    
    protected function createTwilioDataSource(): DataSource
    {
        return DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);
    }
    
    protected function createTestEnterpriseHost(array $attributes = []): EnterpriseHost
    {
        $defaults = [
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'enabled' => true,
            'callback_url' => 'https://example.com/wctp',
            'phone_numbers' => ['+15551234567'],
            'message_count' => 0,
        ];
        
        return EnterpriseHost::create(array_merge($defaults, $attributes));
    }
    
    protected function assertTwilioMessageSent(string $to, ?string $messageContent = null): void
    {
        $this->assertTrue(
            MockTwilioService::assertMessageSent($to, $messageContent),
            "Failed asserting that a message was sent to {$to}" . 
            ($messageContent ? " containing '{$messageContent}'" : '')
        );
    }
    
    protected function assertTwilioMessageNotSent(): void
    {
        $this->assertEmpty(
            MockTwilioService::$sentMessages,
            'Failed asserting that no messages were sent'
        );
    }
    
    protected function simulateTwilioFailure(): void
    {
        MockTwilioService::$shouldFail = true;
    }
    
    protected function getLastTwilioMessage(): ?array
    {
        return MockTwilioService::getLastMessage();
    }
    
    protected function getTwilioCallbackUrl(string $messageId): string
    {
        return route('wctp.callback', ['messageId' => $messageId]);
    }
    
    protected function simulateTwilioCallback(string $messageId, string $status = 'delivered'): void
    {
        $this->post($this->getTwilioCallbackUrl($messageId), [
            'MessageStatus' => $status,
            'MessageSid' => MockTwilioService::$lastMessageSid ?? 'SM' . uniqid(),
            'ErrorCode' => $status === 'failed' ? '30003' : null,
        ]);
    }
}