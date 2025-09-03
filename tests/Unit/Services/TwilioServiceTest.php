<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\TwilioService;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Twilio\Rest\Client;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Exception;
use Illuminate\Support\Facades\Log;

class TwilioServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_initialization_with_valid_datasource(): void
    {
        // Create a valid DataSource
        $dataSource = DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        $service = new TwilioService();
        
        // Access private property to simulate initialization
        $reflection = new \ReflectionClass($service);
        $dataSourceProperty = $reflection->getProperty('dataSource');
        $dataSourceProperty->setAccessible(true);
        $dataSourceProperty->setValue($service, $dataSource);
        
        // Test that we can get the from number
        $this->assertEquals('+15551234567', $service->getFromNumber());
    }

    public function test_initialization_fails_with_no_datasource(): void
    {
        // No DataSource exists
        $service = new TwilioService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No DataSource configured');

        $service->sendSms('+15551234567', 'Test message');
    }

    public function test_initialization_fails_with_missing_credentials(): void
    {
        // Create DataSource with missing credentials
        DataSource::create([
            'twilio_account_sid' => null,
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        $service = new TwilioService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Twilio credentials not configured in DataSource');

        $service->sendSms('+15551234567', 'Test message');
    }

    public function test_initialization_fails_with_missing_from_number(): void
    {
        // Create DataSource without from number
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => null,
        ]);

        $service = new TwilioService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Twilio credentials not configured in DataSource');

        $service->sendSms('+15551234567', 'Test message');
    }

    public function test_send_sms_success(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        // Mock Twilio Client and Message
        $mockMessage = Mockery::mock(MessageInstance::class);
        $mockMessage->shouldReceive('getAttribute')
            ->with('sid')
            ->andReturn('SM123456789abcdef');
        $mockMessage->shouldReceive('getAttribute')
            ->with('to')
            ->andReturn('+15559876543');
        $mockMessage->shouldReceive('getAttribute')
            ->with('from')
            ->andReturn('+15551234567');
        $mockMessage->shouldReceive('getAttribute')
            ->with('status')
            ->andReturn('queued');
        $mockMessage->shouldReceive('getAttribute')
            ->with('dateSent')
            ->andReturn(null);
        $mockMessage->shouldReceive('getAttribute')
            ->with('errorCode')
            ->andReturn(null);
        $mockMessage->shouldReceive('getAttribute')
            ->with('errorMessage')
            ->andReturn(null);

        $mockMessage->sid = 'SM123456789abcdef';
        $mockMessage->to = '+15559876543';
        $mockMessage->from = '+15551234567';
        $mockMessage->status = 'queued';
        $mockMessage->dateSent = null;
        $mockMessage->errorCode = null;
        $mockMessage->errorMessage = null;

        $mockMessageList = Mockery::mock(MessageList::class);
        $mockMessageList->shouldReceive('create')
            ->once()
            ->with('+15559876543', [
                'from' => '+15551234567',
                'body' => 'Test message',
            ])
            ->andReturn($mockMessage);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->messages = $mockMessageList;

        // Replace the service's client with our mock
        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);
        $fromProperty = $reflection->getProperty('fromNumber');
        $fromProperty->setAccessible(true);
        $fromProperty->setValue($service, '+15551234567');

        $result = $service->sendSms('5559876543', 'Test message');

        $this->assertTrue($result['success']);
        $this->assertEquals('SM123456789abcdef', $result['message_sid']);
        $this->assertEquals('+15559876543', $result['to']);
        $this->assertEquals('+15551234567', $result['from']);
        $this->assertEquals('queued', $result['status']);
    }

    public function test_send_sms_with_status_callback(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        // Mock Twilio Message
        $mockMessage = Mockery::mock(MessageInstance::class);
        $mockMessage->sid = 'SM123456789abcdef';
        $mockMessage->to = '+15559876543';
        $mockMessage->from = '+15551234567';
        $mockMessage->status = 'queued';
        $mockMessage->dateSent = null;
        $mockMessage->errorCode = null;
        $mockMessage->errorMessage = null;

        $mockMessageList = Mockery::mock(MessageList::class);
        $mockMessageList->shouldReceive('create')
            ->once()
            ->with('+15559876543', [
                'from' => '+15551234567',
                'body' => 'Test message',
                'statusCallback' => 'https://example.com/callback',
            ])
            ->andReturn($mockMessage);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->messages = $mockMessageList;

        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);
        $fromProperty = $reflection->getProperty('fromNumber');
        $fromProperty->setAccessible(true);
        $fromProperty->setValue($service, '+15551234567');

        $result = $service->sendSms('5559876543', 'Test message', [
            'statusCallback' => 'https://example.com/callback'
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_send_sms_failure(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        Log::shouldReceive('error')
            ->once()
            ->with('Twilio SMS send failed', Mockery::type('array'));

        $mockMessageList = Mockery::mock(MessageList::class);
        $mockMessageList->shouldReceive('create')
            ->once()
            ->andThrow(new Exception('API Error'));

        $mockClient = Mockery::mock(Client::class);
        $mockClient->messages = $mockMessageList;

        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);
        $fromProperty = $reflection->getProperty('fromNumber');
        $fromProperty->setAccessible(true);
        $fromProperty->setValue($service, '+15551234567');

        $result = $service->sendSms('5559876543', 'Test message');

        $this->assertFalse($result['success']);
        $this->assertEquals('API Error', $result['error']);
    }

    public function test_format_phone_number(): void
    {
        $service = new TwilioService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('formatPhoneNumber');
        $method->setAccessible(true);

        // Test 10-digit US number
        $result = $method->invoke($service, '5551234567');
        $this->assertEquals('+15551234567', $result);

        // Test number with formatting
        $result = $method->invoke($service, '(555) 123-4567');
        $this->assertEquals('+15551234567', $result);

        // Test 11-digit US number
        $result = $method->invoke($service, '15551234567');
        $this->assertEquals('+15551234567', $result);

        // Test number that already has +
        $result = $method->invoke($service, '+15551234567');
        $this->assertEquals('+15551234567', $result);

        // Test international number
        $result = $method->invoke($service, '442071234567');
        $this->assertEquals('+442071234567', $result);
    }

    public function test_get_message_status_success(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        $dateSent = new \DateTime('2023-01-01 12:00:00');
        $dateUpdated = new \DateTime('2023-01-01 12:05:00');

        // Mock Message Instance
        $mockMessage = Mockery::mock(MessageInstance::class);
        $mockMessage->status = 'delivered';
        $mockMessage->errorCode = null;
        $mockMessage->errorMessage = null;
        $mockMessage->dateSent = $dateSent;
        $mockMessage->dateUpdated = $dateUpdated;

        $mockMessageList = Mockery::mock(MessageList::class);
        $mockMessageList->shouldReceive('__call')
            ->with('SM123456789abcdef', [])
            ->andReturn($mockMessage);
        $mockMessage->shouldReceive('fetch')
            ->once()
            ->andReturn($mockMessage);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('messages')
            ->with('SM123456789abcdef')
            ->andReturn($mockMessage);

        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);

        $result = $service->getMessageStatus('SM123456789abcdef');

        $this->assertTrue($result['success']);
        $this->assertEquals('delivered', $result['status']);
        $this->assertNull($result['error_code']);
        $this->assertNull($result['error_message']);
        $this->assertEquals($dateSent, $result['date_sent']);
        $this->assertEquals($dateUpdated, $result['date_updated']);
    }

    public function test_get_message_status_failure(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->shouldReceive('messages')
            ->with('SM123456789abcdef')
            ->andThrow(new Exception('Message not found'));

        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);

        $result = $service->getMessageStatus('SM123456789abcdef');

        $this->assertFalse($result['success']);
        $this->assertEquals('Message not found', $result['error']);
    }

    public function test_get_from_number_from_datasource(): void
    {
        $dataSource = DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        $service = new TwilioService();
        
        // Access private properties to simulate initialization
        $reflection = new \ReflectionClass($service);
        $dataSourceProperty = $reflection->getProperty('dataSource');
        $dataSourceProperty->setAccessible(true);
        $dataSourceProperty->setValue($service, $dataSource);

        $this->assertEquals('+15551234567', $service->getFromNumber());
    }

    public function test_get_from_number_returns_null_when_no_datasource(): void
    {
        $service = new TwilioService();
        
        $this->assertNull($service->getFromNumber());
    }

    public function test_send_sms_with_custom_from_number(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        // Mock Twilio Message
        $mockMessage = Mockery::mock(MessageInstance::class);
        $mockMessage->sid = 'SM123456789abcdef';
        $mockMessage->to = '+15559876543';
        $mockMessage->from = '+15559999999';
        $mockMessage->status = 'queued';
        $mockMessage->dateSent = null;
        $mockMessage->errorCode = null;
        $mockMessage->errorMessage = null;

        $mockMessageList = Mockery::mock(MessageList::class);
        $mockMessageList->shouldReceive('create')
            ->once()
            ->with('+15559876543', [
                'from' => '+15559999999',
                'body' => 'Test message',
            ])
            ->andReturn($mockMessage);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->messages = $mockMessageList;

        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);
        $fromProperty = $reflection->getProperty('fromNumber');
        $fromProperty->setAccessible(true);
        $fromProperty->setValue($service, '+15551234567');

        $result = $service->sendSms('5559876543', 'Test message', [
            'from' => '+15559999999'
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('+15559999999', $result['from']);
    }

    public function test_send_sms_with_media_url(): void
    {
        // Create valid DataSource
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        // Mock Twilio Message
        $mockMessage = Mockery::mock(MessageInstance::class);
        $mockMessage->sid = 'SM123456789abcdef';
        $mockMessage->to = '+15559876543';
        $mockMessage->from = '+15551234567';
        $mockMessage->status = 'queued';
        $mockMessage->dateSent = null;
        $mockMessage->errorCode = null;
        $mockMessage->errorMessage = null;

        $mockMessageList = Mockery::mock(MessageList::class);
        $mockMessageList->shouldReceive('create')
            ->once()
            ->with('+15559876543', [
                'from' => '+15551234567',
                'body' => 'Test message',
                'mediaUrl' => 'https://example.com/image.jpg',
            ])
            ->andReturn($mockMessage);

        $mockClient = Mockery::mock(Client::class);
        $mockClient->messages = $mockMessageList;

        $service = Mockery::mock(TwilioService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('initialize')->once();
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($service, $mockClient);
        $fromProperty = $reflection->getProperty('fromNumber');
        $fromProperty->setAccessible(true);
        $fromProperty->setValue($service, '+15551234567');

        $result = $service->sendSms('5559876543', 'Test message', [
            'mediaUrl' => 'https://example.com/image.jpg'
        ]);

        $this->assertTrue($result['success']);
    }

    public function test_initialization_is_lazy(): void
    {
        // Create valid DataSource
        $dataSource = DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);

        $service = new TwilioService();
        
        // Client should be null until first use
        $reflection = new \ReflectionClass($service);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        
        $this->assertNull($clientProperty->getValue($service));
        
        // getFromNumber() doesn't initialize the client, it just reads dataSource
        // Let's test that accessing the dataSource directly works
        $dataSourceProperty = $reflection->getProperty('dataSource');
        $dataSourceProperty->setAccessible(true);
        $dataSourceProperty->setValue($service, $dataSource);
        
        // The from number should now be available
        $this->assertEquals('+15551234567', $service->getFromNumber());
    }

    public function test_datasource_encryption_decryption(): void
    {
        $accountSid = 'test_account_sid_123';
        $authToken = 'test_auth_token_456';
        
        $dataSource = DataSource::create([
            'twilio_account_sid' => encrypt($accountSid),
            'twilio_auth_token' => encrypt($authToken),
            'twilio_from_number' => '+15551234567',
        ]);

        // Verify the encrypted values in the database are different from plain text
        $rawData = \DB::table('data_sources')->where('id', $dataSource->id)->first();
        $this->assertNotEquals($accountSid, $rawData->twilio_account_sid);
        $this->assertNotEquals($authToken, $rawData->twilio_auth_token);

        // Verify the service can decrypt them properly
        $decryptedSid = decrypt($dataSource->twilio_account_sid);
        $decryptedToken = decrypt($dataSource->twilio_auth_token);
        
        $this->assertEquals($accountSid, $decryptedSid);
        $this->assertEquals($authToken, $decryptedToken);
    }
}