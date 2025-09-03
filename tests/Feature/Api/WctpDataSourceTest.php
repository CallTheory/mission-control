<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\EnterpriseHost;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WctpDataSourceTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_wctp_requires_datasource_configuration()
    {
        // Do NOT create a DataSource - system should reject the request
        
        // Create an enterprise host without phone numbers
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'testhost',
            'securityCode' => 'testcode123',
            'type' => 'wctp',
            'enabled' => true,
            'phone_numbers' => null,
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
        
        // Debug output if not 503
        if ($response->status() !== 503) {
            dump($response->content());
        }
        
        // Should return 503 Service Unavailable when DataSource is not configured
        $response->assertStatus(503);
        $this->assertStringContainsString('Service unavailable', $response->content());
    }

    public function test_twilio_service_fails_without_datasource()
    {
        // Do NOT create a DataSource
        
        $twilioService = new \App\Services\TwilioService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No DataSource configured');
        
        // This should throw an exception when trying to initialize without DataSource
        $twilioService->sendSms('+15551234567', 'Test message');
    }

    public function test_twilio_service_fails_with_incomplete_datasource()
    {
        // Create DataSource with missing credentials
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            // Missing auth token and from number
        ]);
        
        $twilioService = new \App\Services\TwilioService();
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Twilio credentials not configured in DataSource');
        
        // This should throw an exception when credentials are incomplete
        $twilioService->sendSms('+15551234567', 'Test message');
    }
}