<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendFaxRingCentral;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendFaxRingCentralTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock DataSource for testing
        DataSource::create([
            'ringcentral_client_id' => 'test-client-id',
            'ringcentral_client_secret' => encrypt('test-client-secret'),
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => encrypt('test-jwt-token'),
        ]);
    }

    /**
     * Test that phone numbers with single commas are cleaned properly
     */
    public function test_phone_number_with_single_comma_is_cleaned(): void
    {
        $fax = [
            'jobID' => 1,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '555,123,4567',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('5551234567', $job->phone);
    }

    /**
     * Test that phone numbers with multiple consecutive commas are cleaned properly
     */
    public function test_phone_number_with_multiple_commas_is_cleaned(): void
    {
        $fax = [
            'jobID' => 2,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '555,,,123,,,4567',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('5551234567', $job->phone);
    }

    /**
     * Test that phone numbers with various formatting characters are cleaned
     */
    public function test_phone_number_with_mixed_formatting_is_cleaned(): void
    {
        $fax = [
            'jobID' => 3,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '(555) 123-4567,,,',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('5551234567', $job->phone);
    }

    /**
     * Test that phone numbers with dots and spaces are cleaned
     */
    public function test_phone_number_with_dots_and_spaces_is_cleaned(): void
    {
        $fax = [
            'jobID' => 4,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '555.123.4567',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('5551234567', $job->phone);
    }

    /**
     * Test international number with commas
     */
    public function test_international_number_with_commas_is_cleaned(): void
    {
        $fax = [
            'jobID' => 5,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '+1,555,123,4567',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('+15551234567', $job->phone);
    }

    /**
     * Test 915554567890 format with commas (handles numbers starting with 9 for dialing out)
     */
    public function test_number_with_9_prefix_and_commas_is_cleaned(): void
    {
        $fax = [
            'jobID' => 6,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '9,1,555,456,7890',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('915554567890', $job->phone);
    }

    /**
     * Test 915554567890 format without commas (special handling for 12 digit numbers starting with 91)
     */
    public function test_number_with_91_prefix_is_cleaned(): void
    {
        $fax = [
            'jobID' => 7,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '915554567890',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('915554567890', $job->phone);
    }

    /**
     * Test 95554567890 format (11 digits starting with 9)
     */
    public function test_number_with_single_9_prefix_is_cleaned(): void
    {
        $fax = [
            'jobID' => 8,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '95554567890',
            'status' => 'pending',
            'fsFileName' => 'test.fs',
        ];

        $job = new SendFaxRingCentral($fax);

        $this->assertEquals('95554567890', $job->phone);
    }
}
