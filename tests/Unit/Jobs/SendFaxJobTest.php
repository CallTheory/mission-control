<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SendFaxJob;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendFaxJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock DataSource for testing
        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'mfax_cover_page_id' => 'test-cover-page',
            'mfax_subject' => 'Test Subject',
            'mfax_sender_name' => 'Test Sender',
            'mfax_notes' => 'Test notes',
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

        $job = new SendFaxJob($fax);

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

        $job = new SendFaxJob($fax);

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

        $job = new SendFaxJob($fax);

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

        $job = new SendFaxJob($fax);

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

        $job = new SendFaxJob($fax);

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

        $job = new SendFaxJob($fax);

        $this->assertEquals('915554567890', $job->phone);
    }

    /**
     * A stray trailing ';' from the .fs file must be stripped.
     */
    public function test_phone_number_with_trailing_semicolon_is_cleaned(): void
    {
        $fax = [
            'jobID' => 7,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '9139069098;',
            'status' => 'pending',
            'fsFileName' => 'IS20.fs',
        ];

        $job = new SendFaxJob($fax);

        $this->assertEquals('9139069098', $job->phone);
    }

    /**
     * The unique lock must be keyed on the per-recipient .fs filename, not the shared
     * jobID, so a fan-out (one .cap to several numbers) reaches every recipient.
     */
    public function test_unique_id_is_keyed_on_fs_filename(): void
    {
        $fax = [
            'jobID' => 7,
            'capfile' => 'test.cap',
            'filename' => 'test.txt',
            'phone' => '9133597692',
            'status' => 'pending',
            'fsFileName' => 'IS21.fs',
        ];

        $job = new SendFaxJob($fax);

        $this->assertEquals('IS21.fs', $job->uniqueId());
    }
}
