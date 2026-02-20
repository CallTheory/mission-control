<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\ProcessWctpMessage;
use App\Models\DataSource;
use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;
use Tests\Traits\MocksTwilio;

class WctpControllerComprehensiveTest extends TestCase
{
    use MocksTwilio, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable the WCTP gateway feature flag
        \Storage::put('feature-flags/wctp-gateway.flag', encrypt('wctp-gateway'));

        // Register WCTP routes for testing
        $this->app['router']->post('/wctp', [\App\Http\Controllers\Api\WctpController::class, 'handle'])
            ->name('wctp');
        $this->app['router']->post('/wctp/callback/{messageId}', [\App\Http\Controllers\Api\WctpController::class, 'twilioCallback'])
            ->name('wctp.callback');

        // Set up Twilio mock
        $this->setUpTwilioMock();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_empty_request_body_returns_400(): void
    {
        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], '');

        $response->assertStatus(400);
        $this->assertStringStartsWith('text/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->content());
        $this->assertEquals('301', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('No WCTP message provided', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_invalid_xml_returns_400(): void
    {
        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], 'invalid xml content');

        $response->assertStatus(400);
        $this->assertStringStartsWith('text/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->content());
        $this->assertEquals('301', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Invalid XML syntax', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_unsupported_operation_returns_400(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-UnsupportedOperation/>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(400);
        $this->assertStringStartsWith('text/xml', $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->content());
        $this->assertEquals('302', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('No valid WCTP operation found', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_submit_request_without_recipient_id_returns_403(): void
    {
        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-Recipient recipientID=""/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(403);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('403', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Invalid recipientID', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_submit_request_without_message_returns_411(): void
    {
        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message></wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(400);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('411', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Message is required', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_submit_request_without_sender_id_returns_401(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(401);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('401', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Invalid senderID', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_submit_request_with_nonexistent_sender_id_returns_401(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="nonexistent" securityCode="testcode123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(401);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('401', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('sender not found', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_submit_request_with_wrong_security_code_returns_401(): void
    {
        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'correctcode123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="wrongcode"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(401);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('402', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Invalid securityCode', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_submit_request_with_disabled_host_returns_401(): void
    {
        $host = EnterpriseHost::factory()->disabled()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(401);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('401', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
    }

    public function test_submit_request_without_twilio_configuration_returns_503(): void
    {
        // Remove the DataSource
        DataSource::truncate();

        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'phone_numbers' => null,
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(503);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('503', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Service unavailable', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_successful_submit_request_with_reply_code_extraction(): void
    {
        Queue::fake();

        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-Recipient recipientID="(555) 123-4567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Please confirm your order. Reply with 456</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);

        // Verify message was created with cleaned phone number and extracted reply code
        $this->assertDatabaseHas('wctp_messages', [
            'enterprise_host_id' => $host->id,
            'to' => '5551234567', // Phone number should be cleaned
            'reply_with' => '456', // Reply code should be extracted
            'wctp_message_id' => 'test123',
            'status' => 'queued',
        ]);

        // Verify message content (encrypted at rest, so check via model)
        $wctpMessage = WctpMessage::where('wctp_message_id', 'test123')->first();
        $this->assertEquals('Please confirm your order. Reply with 456', $wctpMessage->message);

        // Verify job was dispatched
        Queue::assertPushed(ProcessWctpMessage::class);

        // Verify host statistics were updated
        $host->refresh();
        $this->assertEquals(1, $host->message_count);
        $this->assertNotNull($host->last_message_at);
    }

    public function test_client_query_for_existing_message(): void
    {
        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
        ]);

        $message = WctpMessage::factory()->delivered()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'test123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-TrackingNumber>test123</wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('test123', (string) $xml->{'wctp-StatusInfo'}['messageID']);
        $this->assertEquals('200', (string) $xml->{'wctp-StatusInfo'}->{'wctp-Notification'}['notificationCode']);
        $this->assertStringContainsString('delivered', (string) $xml->{'wctp-StatusInfo'}->{'wctp-Notification'}['notificationText']);
    }

    public function test_client_query_for_nonexistent_message(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-TrackingNumber>nonexistent123</wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('nonexistent123', (string) $xml->{'wctp-StatusInfo'}['messageID']);
        $this->assertEquals('404', (string) $xml->{'wctp-StatusInfo'}->{'wctp-Notification'}['notificationCode']);
        $this->assertStringContainsString('not found', (string) $xml->{'wctp-StatusInfo'}->{'wctp-Notification'}['notificationText']);
    }

    public function test_client_query_without_tracking_number_returns_400(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-TrackingNumber></wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(400);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('400', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Tracking number is required', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_client_query_with_authentication_failure_returns_401(): void
    {
        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'correctcode',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="wrongcode"/>
            <wctp-TrackingNumber>test123</wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(401);
        $xml = simplexml_load_string($response->content());
        $this->assertEquals('401', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
        $this->assertStringContainsString('Authentication failed', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorText']);
    }

    public function test_client_query_updates_message_status_from_twilio(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'test123',
            'twilio_sid' => 'SM123456789',
            'status' => 'sent',
        ]);

        // Put status in cache as if Twilio callback had occurred
        cache()->put('wctp_status_test123', 'delivered', now()->addMinutes(60));

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-TrackingNumber>test123</wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);

        // Verify message status was updated from cache
        $message->refresh();
        $this->assertEquals('delivered', $message->status);
    }

    public function test_message_reply_handling(): void
    {
        // Create an original message to reply to
        $host = EnterpriseHost::factory()->create();
        $originalMessage = WctpMessage::factory()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'original123',
            'direction' => 'outbound',
            'status' => 'delivered',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-MessageReply responseToMessageID="original123" responseText="YES" submitTimestamp="2023-01-01T12:00:00Z"/>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $xml = simplexml_load_string($response->content());
        $this->assertNotNull($xml->{'wctp-Confirmation'}->{'wctp-Success'});

        // Check that a reply was created (message is encrypted at rest)
        $this->assertDatabaseHas('wctp_messages', [
            'parent_message_id' => $originalMessage->id,
            'direction' => 'inbound',
        ]);

        // Verify message content via model
        $reply = WctpMessage::where('parent_message_id', $originalMessage->id)->first();
        $this->assertEquals('YES', $reply->message);
    }

    public function test_twilio_callback_updates_message_by_wctp_id(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp123',
            'twilio_sid' => 'SM123456789',
        ]);

        $response = $this->post('/wctp/callback/wctp123', [
            'MessageSid' => 'SM123456789',
            'MessageStatus' => 'delivered',
            'To' => '+15551234567',
            'From' => '+15559876543',
        ]);

        $response->assertStatus(204);

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);

        // Verify cache was updated
        $this->assertEquals('delivered', Cache::get('wctp_status_wctp123'));
    }

    public function test_twilio_callback_updates_message_by_carrier_sid(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp123',
            'twilio_sid' => 'SM123456789',
        ]);

        $response = $this->post('/wctp/callback/different_id', [
            'MessageSid' => 'SM123456789',
            'MessageStatus' => 'failed',
            'ErrorCode' => '30005',
            'ErrorMessage' => 'Message blocked',
        ]);

        $response->assertStatus(204);

        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertNotNull($message->failed_at);
    }

    public function test_twilio_callback_handles_different_statuses(): void
    {
        $host = EnterpriseHost::factory()->create();

        // Test 'sent' status
        $message1 = WctpMessage::factory()->pending()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp123',
            'status' => 'pending',
        ]);

        $this->post('/wctp/callback/wctp123', [
            'MessageSid' => 'SM123456789',
            'MessageStatus' => 'sent',
        ]);

        $message1->refresh();
        $this->assertEquals('sent', $message1->status);
        $this->assertNull($message1->delivered_at);
        $this->assertNull($message1->failed_at);

        // Test 'undelivered' status
        $message2 = WctpMessage::factory()->sent()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'wctp456',
        ]);

        $this->post('/wctp/callback/wctp456', [
            'MessageSid' => 'SM987654321',
            'MessageStatus' => 'undelivered',
        ]);

        $message2->refresh();
        $this->assertEquals('failed', $message2->status);
        $this->assertNotNull($message2->failed_at);
    }

    public function test_error_code_mapping(): void
    {
        // Invalid XML triggers a 301 error code with HTTP 400
        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], 'invalid xml');
        $this->assertEquals(400, $response->status());

        $xml = simplexml_load_string($response->content());
        $this->assertEquals('301', (string) $xml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
    }

    public function test_successful_request_processing(): void
    {
        $host = EnterpriseHost::factory()->create([
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
        ]);

        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="testhost" securityCode="testcode123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="test123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Test message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        Queue::fake();
        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $this->assertStringStartsWith('text/xml', $response->headers->get('Content-Type'));

        // Verify it's a success response
        $xml = simplexml_load_string($response->content());
        $this->assertNotNull($xml->{'wctp-Confirmation'}->{'wctp-Success'});
    }
}
