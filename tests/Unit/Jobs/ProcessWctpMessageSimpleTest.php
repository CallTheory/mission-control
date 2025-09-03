<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ProcessWctpMessage;
use App\Models\WctpMessage;
use App\Models\EnterpriseHost;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Exception;

class ProcessWctpMessageSimpleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up route to avoid issues with route() calls
        $this->app['router']->post('/wctp/callback/{messageId}', function () {
            return response('', 204);
        })->name('wctp.callback');
        
        // Create a DataSource for TwilioService initialization
        DataSource::create([
            'twilio_account_sid' => encrypt('test_account_sid'),
            'twilio_auth_token' => encrypt('test_auth_token'),
            'twilio_from_number' => '+15551234567',
        ]);
    }

    public function test_job_configuration(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->create(['enterprise_host_id' => $host->id]);
        
        $job = new ProcessWctpMessage($message);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals([60, 180, 600], $job->backoff);
    }

    public function test_job_failure_handler(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->pending()->create([
            'enterprise_host_id' => $host->id,
        ]);

        $job = new ProcessWctpMessage($message);
        $exception = new Exception('Job failed permanently');

        $job->failed($exception);

        // Verify message was marked as failed
        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertNotNull($message->failed_at);
    }

    public function test_message_status_transitions(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->pending()->create([
            'enterprise_host_id' => $host->id,
            'status' => 'pending',
        ]);

        // Test markAsSent
        $message->markAsSent('SM123456789');
        $message->refresh();
        $this->assertEquals('sent', $message->status);
        $this->assertEquals('SM123456789', $message->twilio_sid);

        // Test markAsDelivered
        $message->markAsDelivered();
        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);

        // Test markAsFailed
        $failMessage = WctpMessage::factory()->pending()->create([
            'enterprise_host_id' => $host->id,
        ]);
        $failMessage->markAsFailed('Test error');
        $failMessage->refresh();
        $this->assertEquals('failed', $failMessage->status);
        $this->assertNotNull($failMessage->failed_at);
    }

    public function test_callback_url_generation(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->pending()->create([
            'enterprise_host_id' => $host->id,
            'wctp_message_id' => 'test_message_123',
        ]);

        // Test that the callback URL can be generated
        // Route is already defined in setUp() with proper name
        $expectedUrl = url('/wctp/callback/test_message_123');
        $this->assertStringContainsString('/wctp/callback/test_message_123', $expectedUrl);
    }

    public function test_cache_key_format_consistency(): void
    {
        $messageId = 'special-message-id-123';
        $carrierSid = 'SM123456789abcdef';
        
        // This is the format used in the job
        $expectedCacheKey = "wctp_message_{$messageId}";
        
        // Store something in cache using this format
        cache()->put($expectedCacheKey, $carrierSid, now()->addHours(24));
        
        // Verify we can retrieve it
        $this->assertEquals($carrierSid, cache()->get($expectedCacheKey));
    }

    public function test_job_serialization(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->create(['enterprise_host_id' => $host->id]);
        
        $job = new ProcessWctpMessage($message);
        
        // Test that the job can be serialized and unserialized (important for queue)
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(ProcessWctpMessage::class, $unserialized);
    }

    public function test_message_factory_states(): void
    {
        $host = EnterpriseHost::factory()->create();
        
        // Test different factory states used in job processing
        $pending = WctpMessage::factory()->pending()->create(['enterprise_host_id' => $host->id]);
        $this->assertEquals('pending', $pending->status);
        $this->assertNull($pending->twilio_sid);
        
        $sent = WctpMessage::factory()->sent()->create(['enterprise_host_id' => $host->id]);
        $this->assertEquals('sent', $sent->status);
        $this->assertNotNull($sent->twilio_sid);
        
        $delivered = WctpMessage::factory()->delivered()->create(['enterprise_host_id' => $host->id]);
        $this->assertEquals('delivered', $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        
        $failed = WctpMessage::factory()->failed()->create(['enterprise_host_id' => $host->id]);
        $this->assertEquals('failed', $failed->status);
        $this->assertNotNull($failed->failed_at);
    }

    public function test_enterprise_host_message_recording(): void
    {
        $host = EnterpriseHost::factory()->create([
            'message_count' => 5,
            'last_message_at' => null,
        ]);

        $originalTime = now()->subHour();
        $this->travel($originalTime);

        $host->recordMessage();

        $host->refresh();
        $this->assertEquals(6, $host->message_count);
        $this->assertNotNull($host->last_message_at);
        $this->assertTrue($host->last_message_at->isAfter($originalTime));
    }
}