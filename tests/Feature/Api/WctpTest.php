<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\EnterpriseHost;
use App\Models\DataSource;
use App\Models\WctpMessage;
use App\Jobs\ProcessWctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\MocksTwilio;

class WctpTest extends TestCase
{
    use RefreshDatabase, MocksTwilio;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Enable the WCTP gateway feature flag
        \Storage::put('feature-flags/wctp-gateway.flag', encrypt('wctp-gateway'));
        
        // Register WCTP routes for testing since the flag wasn't set at boot time
        if (!\Route::has('wctp')) {
            \Route::post('/wctp', [\App\Http\Controllers\Api\WctpController::class, 'handle'])
                ->name('wctp');
            \Route::post('/wctp/callback/{messageId}', [\App\Http\Controllers\Api\WctpController::class, 'twilioCallback'])
                ->name('wctp.callback');
        }
        
        // Set up Twilio mock
        $this->setUpTwilioMock();
    }

    public function test_wctp_submit_request_requires_authentication()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <wctp-Operation wctpVersion="wctp-dtd-v1r3">
            <wctp-SubmitRequest>
                <wctp-SubmitHeader>
                    <wctp-ClientOriginator senderID="testhost" securityCode="wrongcode" />
                    <wctp-Recipient recipientID="5551234567" />
                    <wctp-MessageControl messageID="test123" />
                </wctp-SubmitHeader>
                <wctp-Payload>
                    <wctp-Message>Test message</wctp-Message>
                </wctp-Payload>
            </wctp-SubmitRequest>
        </wctp-Operation>';

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
        
        $response->assertStatus(401);
        $this->assertStringContainsString('wctp-Failure', $response->content());
    }

    public function test_wctp_submit_request_with_valid_host()
    {
        Queue::fake();
        
        // Create an enterprise host
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'type' => 'wctp',
            'enabled' => true,
            'phone_numbers' => ['+15551234567'],
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <wctp-Operation wctpVersion="wctp-dtd-v1r3">
            <wctp-SubmitRequest>
                <wctp-SubmitHeader>
                    <wctp-ClientOriginator senderID="testhost" securityCode="testcode123" />
                    <wctp-Recipient recipientID="5551234567" />
                    <wctp-MessageControl messageID="test123" />
                </wctp-SubmitHeader>
                <wctp-Payload>
                    <wctp-Message>Test message</wctp-Message>
                </wctp-Payload>
            </wctp-SubmitRequest>
        </wctp-Operation>';

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
        
        // Debug: output response if it fails
        if ($response->status() !== 200) {
            dump($response->content());
        }
        
        $response->assertStatus(200);
        $this->assertStringContainsString('wctp-Confirmation', $response->content());
        
        // Check that a message was created (message is encrypted at rest)
        $this->assertDatabaseHas('wctp_messages', [
            'enterprise_host_id' => $host->id,
            'to' => '5551234567',
            'wctp_message_id' => 'test123',
            'status' => 'queued',
        ]);

        // Verify message content via model (encrypted at rest)
        $wctpMessage = WctpMessage::where('wctp_message_id', 'test123')->first();
        $this->assertEquals('Test message', $wctpMessage->message);
        
        // Check that the job was dispatched
        Queue::assertPushed(ProcessWctpMessage::class);
    }

    public function test_wctp_submit_request_with_disabled_host()
    {
        // Create a disabled enterprise host
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'type' => 'wctp',
            'enabled' => false,
            'phone_numbers' => ['+15551234567'],
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <wctp-Operation wctpVersion="wctp-dtd-v1r3">
            <wctp-SubmitRequest>
                <wctp-SubmitHeader>
                    <wctp-ClientOriginator senderID="testhost" securityCode="testcode123" />
                    <wctp-Recipient recipientID="5551234567" />
                    <wctp-MessageControl messageID="test123" />
                </wctp-SubmitHeader>
                <wctp-Payload>
                    <wctp-Message>Test message</wctp-Message>
                </wctp-Payload>
            </wctp-SubmitRequest>
        </wctp-Operation>';

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
        
        $response->assertStatus(401);
        $this->assertStringContainsString('wctp-Failure', $response->content());
        $this->assertStringContainsString('401', $response->content());
    }

    public function test_wctp_client_query()
    {
        // Create an enterprise host
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'type' => 'wctp',
            'enabled' => true,
            'phone_numbers' => ['+15551234567'],
        ]);
        
        // Create a message
        $message = WctpMessage::create([
            'enterprise_host_id' => $host->id,
            'carrier' => 'twilio',
            'to' => '5551234567',
            'from' => '+15551234567',
            'message' => 'Test message',
            'wctp_message_id' => 'test123',
            'direction' => 'outbound',
            'status' => 'delivered',
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <wctp-Operation wctpVersion="wctp-dtd-v1r3">
            <wctp-ClientQuery>
                <wctp-ClientQueryHeader>
                    <wctp-ClientOriginator senderID="testhost" securityCode="testcode123" />
                    <wctp-TrackingNumber>test123</wctp-TrackingNumber>
                </wctp-ClientQueryHeader>
            </wctp-ClientQuery>
        </wctp-Operation>';

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
        
        $response->assertStatus(200);
        $this->assertStringContainsString('wctp-StatusInfo', $response->content());
        $this->assertStringContainsString('200', $response->content()); // Delivered status
    }

    public function test_wctp_message_with_reply_code()
    {
        Queue::fake();
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'type' => 'wctp',
            'enabled' => true,
            'phone_numbers' => ['+15551234567'],
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <wctp-Operation wctpVersion="wctp-dtd-v1r3">
            <wctp-SubmitRequest>
                <wctp-SubmitHeader>
                    <wctp-ClientOriginator senderID="testhost" securityCode="testcode123" />
                    <wctp-Recipient recipientID="5551234567" />
                    <wctp-MessageControl messageID="test123" />
                </wctp-SubmitHeader>
                <wctp-Payload>
                    <wctp-Message>Test message. Reply with 123</wctp-Message>
                </wctp-Payload>
            </wctp-SubmitRequest>
        </wctp-Operation>';

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
        
        $response->assertStatus(200);
        
        // Check that the reply code was extracted
        $this->assertDatabaseHas('wctp_messages', [
            'reply_with' => '123',
        ]);
    }

    public function test_host_message_count_increments()
    {
        Queue::fake();
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'type' => 'wctp',
            'enabled' => true,
            'message_count' => 0,
            'phone_numbers' => ['+15551234567'],
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <wctp-Operation wctpVersion="wctp-dtd-v1r3">
            <wctp-SubmitRequest>
                <wctp-SubmitHeader>
                    <wctp-ClientOriginator senderID="testhost" securityCode="testcode123" />
                    <wctp-Recipient recipientID="5551234567" />
                    <wctp-MessageControl messageID="test123" />
                </wctp-SubmitHeader>
                <wctp-Payload>
                    <wctp-Message>Test message</wctp-Message>
                </wctp-Payload>
            </wctp-SubmitRequest>
        </wctp-Operation>';

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);
        
        $response->assertStatus(200);
        
        // Check that the message count was incremented
        $host->refresh();
        $this->assertEquals(1, $host->message_count);
        $this->assertNotNull($host->last_message_at);
    }
}