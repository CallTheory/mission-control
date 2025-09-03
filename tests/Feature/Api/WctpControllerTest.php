<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Services\TwilioService;
use App\Models\DataSource;
use Tests\Traits\MocksTwilio;
use Mockery;

class WctpControllerTest extends TestCase
{
    use RefreshDatabase, MocksTwilio;

    protected $user;
    protected $team;
    protected $host;
    protected $dataSource;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create the feature flag file for testing
        $encryptedContent = encrypt('enabled');
        \Storage::put('feature-flags/wctp-gateway.flag', $encryptedContent);
        
        // Register WCTP routes for testing since the flag wasn't set at boot time
        if (!\Route::has('wctp')) {
            \Route::post('/wctp', [\App\Http\Controllers\Api\WctpController::class, 'handle'])
                ->name('wctp');
            \Route::post('/wctp/callback/{messageId}', [\App\Http\Controllers\Api\WctpController::class, 'twilioCallback'])
                ->name('wctp.callback');
        }
        
        // Set up Twilio mock
        $this->setUpTwilioMock();
        
        // Create a user and team for the EnterpriseHost
        $this->user = \App\Models\User::factory()->withPersonalTeam()->create();
        $this->team = $this->user->currentTeam;
        
        // Create an EnterpriseHost for testing
        $this->host = \App\Models\EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test@example.com',
            'securityCode' => 'test123', // Test security code
            'enabled' => true,
            'team_id' => $this->team->id,
            'phone_numbers' => ['+15551234567'],
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up the feature flag file
        Storage::delete('feature-flags/wctp-gateway.flag');
        
        Mockery::close();
        parent::tearDown();
    }

    public function test_wctp_endpoint_accepts_submit_request(): void
    {
        // Test the WCTP endpoint accepts a submit request and returns proper XML response

        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-Originator senderID="test@example.com" securityCode="test123"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="msg123" transactionID="txn123" allowResponse="false" notificationRequest="false"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Alphanumeric>Test message</wctp-Alphanumeric>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        
        $responseXml = simplexml_load_string($response->getContent());
        $this->assertNotNull($responseXml);
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}));
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}->{'wctp-Success'}));
    }

    public function test_wctp_endpoint_handles_client_query(): void
    {
        // Store a test message ID in cache
        cache()->put('wctp_message_test123', 'twilio_sid_123', now()->addHours(1));

        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-ClientOriginator senderID="test@example.com" securityCode="test123"/>
            <wctp-TrackingNumber>test123</wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        
        $responseXml = simplexml_load_string($response->getContent());
        $this->assertNotNull($responseXml);
        $this->assertTrue(isset($responseXml->{'wctp-StatusInfo'}));
        $this->assertEquals('test123', (string)$responseXml->{'wctp-StatusInfo'}['messageID']);
    }

    public function test_wctp_endpoint_returns_error_for_invalid_xml(): void
    {
        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], 'invalid xml');

        $response->assertStatus(500);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        
        $responseXml = simplexml_load_string($response->getContent());
        $this->assertNotNull($responseXml);
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}));
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}->{'wctp-Failure'}));
    }

    public function test_wctp_endpoint_returns_error_for_empty_request(): void
    {
        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], '');

        $response->assertStatus(400);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        
        $responseXml = simplexml_load_string($response->getContent());
        $this->assertNotNull($responseXml);
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}));
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}->{'wctp-Failure'}));
        $this->assertEquals('400', (string)$responseXml->{'wctp-Confirmation'}->{'wctp-Failure'}['errorCode']);
    }

    public function test_wctp_endpoint_handles_message_reply(): void
    {
        // Create an original message to reply to
        \App\Models\WctpMessage::create([
            'enterprise_host_id' => $this->host->id,
            'to' => '+15551234567',
            'from' => '+15559999999',
            'message' => 'Original message',
            'wctp_message_id' => 'msg123',
            'direction' => 'outbound',
            'status' => 'delivered',
        ]);

        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-MessageReply responseToMessageID="msg123" responseText="Reply text" submitTimestamp="2024-01-01T12:00:00Z"/>
</wctp-Operation>
XML;

        $response = $this->call('POST', '/wctp', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
        
        $responseXml = simplexml_load_string($response->getContent());
        $this->assertNotNull($responseXml);
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}));
        $this->assertTrue(isset($responseXml->{'wctp-Confirmation'}->{'wctp-Success'}));
    }

    public function test_twilio_callback_updates_message_status(): void
    {
        $response = $this->post('/wctp/callback/test_message_123', [
            'MessageSid' => 'SM123456789',
            'MessageStatus' => 'delivered',
            'To' => '+15551234567',
            'From' => '+15559999999'
        ]);

        $response->assertStatus(204);
        
        // Verify the status was cached
        $cachedStatus = cache()->get('wctp_status_test_message_123');
        $this->assertEquals('delivered', $cachedStatus);
    }
}