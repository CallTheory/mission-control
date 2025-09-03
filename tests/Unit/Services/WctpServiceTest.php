<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\WctpService;
use Exception;

class WctpServiceTest extends TestCase
{
    protected WctpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WctpService();
    }

    public function test_parse_submit_request(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-Originator senderID="sender@example.com"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="msg123" transactionID="txn123" allowResponse="true" notificationRequest="true"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Alphanumeric>Test message content</wctp-Alphanumeric>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-SubmitRequest', $result['operation']);
        $this->assertEquals('sender@example.com', $result['data']['sender_id']);
        $this->assertEquals('5551234567', $result['data']['recipient_id']);
        $this->assertEquals('Test message content', $result['data']['message']);
        $this->assertEquals('alphanumeric', $result['data']['message_type']);
        $this->assertEquals('msg123', $result['data']['message_control']['message_id']);
        $this->assertEquals('txn123', $result['data']['message_control']['transaction_id']);
        $this->assertEquals('true', $result['data']['message_control']['allow_response']);
        $this->assertEquals('true', $result['data']['message_control']['notification_requested']);
    }

    public function test_parse_client_query(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-ClientQueryHeader>
            <wctp-ClientOriginator senderID="sender@example.com" securityCode="secret123"/>
            <wctp-TrackingNumber>track123</wctp-TrackingNumber>
        </wctp-ClientQueryHeader>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-ClientQuery', $result['operation']);
        $this->assertEquals('sender@example.com', $result['data']['sender_id']);
        $this->assertEquals('secret123', $result['data']['security_code']);
        $this->assertEquals('track123', $result['data']['tracking_number']);
    }

    public function test_parse_message_reply(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-MessageReply responseToMessageID="msg123" responseText="Reply text" submitTimestamp="2024-01-01T12:00:00Z"/>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-MessageReply', $result['operation']);
        $this->assertEquals('msg123', $result['data']['response_to_message_id']);
        $this->assertEquals('Reply text', $result['data']['response_text']);
        $this->assertEquals('2024-01-01T12:00:00Z', $result['data']['submit_time_stamp']);
    }

    public function test_parse_transparent_data(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-Originator senderID="sender@example.com"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="msg123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-TransparentData data="SGVsbG8gV29ybGQ=" encoding="base64"/>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-SubmitRequest', $result['operation']);
        $this->assertEquals('SGVsbG8gV29ybGQ=', $result['data']['message']);
        $this->assertEquals('transparent', $result['data']['message_type']);
        $this->assertEquals('base64', $result['data']['encoding']);
    }

    public function test_parse_invalid_xml_throws_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse WCTP message');

        $this->service->parseWctpMessage('invalid xml');
    }

    public function test_parse_unsupported_operation_throws_exception(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-UnsupportedOperation/>
</wctp-Operation>
XML;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No valid WCTP operation found');

        $this->service->parseWctpMessage($xml);
    }

    public function test_create_confirmation(): void
    {
        $xml = $this->service->createConfirmation('msg123', '200', 'Success');
        
        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('wctp-Confirmation', $xml);
        $this->assertStringContainsString('wctp-Success', $xml);
        $this->assertStringContainsString('successCode="200"', $xml);
        $this->assertStringContainsString('successText="Success"', $xml);
        $this->assertStringContainsString('messageID="msg123"', $xml);
    }

    public function test_create_failure(): void
    {
        $xml = $this->service->createFailure('400', 'Bad Request');
        
        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('wctp-Confirmation', $xml);
        $this->assertStringContainsString('wctp-Failure', $xml);
        $this->assertStringContainsString('errorCode="400"', $xml);
        $this->assertStringContainsString('errorText="Bad Request"', $xml);
    }

    public function test_create_status_info(): void
    {
        $xml = $this->service->createStatusInfo('msg123', '200', 'Delivered');
        
        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('wctp-StatusInfo', $xml);
        $this->assertStringContainsString('messageID="msg123"', $xml);
        $this->assertStringContainsString('wctp-Notification', $xml);
        $this->assertStringContainsString('notificationCode="200"', $xml);
        $this->assertStringContainsString('notificationText="Delivered"', $xml);
    }

    public function test_parse_submit_request_with_client_originator(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-ClientOriginator senderID="enterprise123" securityCode="secret456"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="msg123" allowResponse="false" notificationRequest="true"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Enterprise message content</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-SubmitRequest', $result['operation']);
        $this->assertEquals('enterprise123', $result['data']['sender_id']);
        $this->assertEquals('secret456', $result['data']['security_code']);
        $this->assertEquals('5551234567', $result['data']['recipient_id']);
        $this->assertEquals('Enterprise message content', $result['data']['message']);
        $this->assertEquals('message', $result['data']['message_type']);
        $this->assertEquals('msg123', $result['data']['message_control']['message_id']);
        $this->assertEquals('false', $result['data']['message_control']['allow_response']);
        $this->assertEquals('true', $result['data']['message_control']['notification_requested']);
    }

    public function test_parse_submit_request_without_originator(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="msg123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
            <wctp-Message>Anonymous message</wctp-Message>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-SubmitRequest', $result['operation']);
        $this->assertEquals('', $result['data']['sender_id']);
        $this->assertEquals('', $result['data']['security_code']);
        $this->assertEquals('Anonymous message', $result['data']['message']);
    }

    public function test_parse_client_query_without_originator(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-ClientQuery>
        <wctp-TrackingNumber>track123</wctp-TrackingNumber>
    </wctp-ClientQuery>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-ClientQuery', $result['operation']);
        $this->assertEquals('', $result['data']['sender_id']);
        $this->assertEquals('', $result['data']['security_code']);
        $this->assertEquals('track123', $result['data']['tracking_number']);
    }

    public function test_parse_empty_message_elements(): void
    {
        $xml = <<<XML
<?xml version="1.0"?>
<!DOCTYPE wctp-Operation SYSTEM "http://www.wctp.org/release/wctp-dtd-v1r3.dtd">
<wctp-Operation wctpVersion="1.3">
    <wctp-SubmitRequest>
        <wctp-SubmitHeader>
            <wctp-Originator senderID="sender@example.com"/>
            <wctp-Recipient recipientID="5551234567"/>
            <wctp-MessageControl messageID="msg123"/>
        </wctp-SubmitHeader>
        <wctp-Payload>
        </wctp-Payload>
    </wctp-SubmitRequest>
</wctp-Operation>
XML;

        $result = $this->service->parseWctpMessage($xml);

        $this->assertEquals('wctp-SubmitRequest', $result['operation']);
        $this->assertArrayNotHasKey('message', $result['data']);
        $this->assertArrayNotHasKey('message_type', $result['data']);
    }

    public function test_create_submit_request_with_notifius_library(): void
    {
        $xml = $this->service->createSubmitRequest('sender123', '5551234567', 'Test message', 'msg123');
        
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('wctp-SubmitRequest', $xml);
        $this->assertStringContainsString('sender123', $xml);
        $this->assertStringContainsString('5551234567', $xml);
        $this->assertStringContainsString('Test message', $xml);
        $this->assertStringContainsString('msg123', $xml);
    }

    public function test_create_client_query_with_notifius_library(): void
    {
        $xml = $this->service->createClientQuery('sender123', '5551234567', 'track123');
        
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('wctp-ClientQuery', $xml);
        $this->assertStringContainsString('sender123', $xml);
        $this->assertStringContainsString('5551234567', $xml);
        $this->assertStringContainsString('track123', $xml);
    }

    public function test_create_submit_request_generates_message_id_if_not_provided(): void
    {
        $xml = $this->service->createSubmitRequest('sender123', '5551234567', 'Test message');
        
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('wctp-SubmitRequest', $xml);
        $this->assertStringContainsString('wctp_', $xml); // Should contain generated ID prefix
    }

    public function test_parse_malformed_xml_structure(): void
    {
        $xml = 'not valid xml at all';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse WCTP message');

        $this->service->parseWctpMessage($xml);
    }

    public function test_dtd_constants(): void
    {
        $this->assertEquals('1.3', WctpService::WCTP_VERSION);
        $this->assertEquals('http://www.wctp.org/release/wctp-dtd-v1r3.dtd', WctpService::DTD_URL);
    }

    public function test_xml_response_structure(): void
    {
        $xml = $this->service->createConfirmation('msg123', '200', 'Success');
        
        $simpleXml = new \SimpleXMLElement($xml);
        $this->assertEquals('1.3', (string) $simpleXml['wctpVersion']);
        $this->assertNotNull($simpleXml->{'wctp-Confirmation'});
        $this->assertNotNull($simpleXml->{'wctp-Confirmation'}->{'wctp-Success'});
        
        $success = $simpleXml->{'wctp-Confirmation'}->{'wctp-Success'};
        $this->assertEquals('200', (string) $success['successCode']);
        $this->assertEquals('Success', (string) $success['successText']);
        $this->assertEquals('msg123', (string) $success['messageID']);
    }

    public function test_error_response_structure(): void
    {
        $xml = $this->service->createFailure('400', 'Bad Request');
        
        $simpleXml = new \SimpleXMLElement($xml);
        $this->assertEquals('1.3', (string) $simpleXml['wctpVersion']);
        $this->assertNotNull($simpleXml->{'wctp-Confirmation'});
        $this->assertNotNull($simpleXml->{'wctp-Confirmation'}->{'wctp-Failure'});
        
        $failure = $simpleXml->{'wctp-Confirmation'}->{'wctp-Failure'};
        $this->assertEquals('400', (string) $failure['errorCode']);
        $this->assertEquals('Bad Request', (string) $failure['errorText']);
    }
}