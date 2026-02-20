<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WctpMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_enterprise_host_relationship(): void
    {
        $host = EnterpriseHost::factory()->create();
        $message = WctpMessage::factory()->create(['enterprise_host_id' => $host->id]);

        $this->assertInstanceOf(EnterpriseHost::class, $message->enterpriseHost);
        $this->assertEquals($host->id, $message->enterpriseHost->id);
    }

    public function test_pending_scope(): void
    {
        WctpMessage::factory()->pending()->create();
        WctpMessage::factory()->sent()->create();
        WctpMessage::factory()->delivered()->create();

        $pendingMessages = WctpMessage::pending()->get();

        $this->assertCount(1, $pendingMessages);
        $this->assertEquals('pending', $pendingMessages->first()->status);
    }

    public function test_outbound_scope(): void
    {
        WctpMessage::factory()->create(['direction' => 'outbound']);
        WctpMessage::factory()->inbound()->create();

        $outboundMessages = WctpMessage::outbound()->get();

        $this->assertCount(1, $outboundMessages);
        $this->assertEquals('outbound', $outboundMessages->first()->direction);
    }

    public function test_inbound_scope(): void
    {
        WctpMessage::factory()->create(['direction' => 'outbound']);
        WctpMessage::factory()->inbound()->create();

        $inboundMessages = WctpMessage::inbound()->get();

        $this->assertCount(1, $inboundMessages);
        $this->assertEquals('inbound', $inboundMessages->first()->direction);
    }

    public function test_mark_as_sent(): void
    {
        $message = WctpMessage::factory()->pending()->create([
            'status' => 'pending',
            'twilio_sid' => null,
        ]);

        $twilioSid = 'SM123456789abcdef';
        $message->markAsSent($twilioSid);

        $message->refresh();
        $this->assertEquals('sent', $message->status);
        $this->assertEquals($twilioSid, $message->twilio_sid);
    }

    public function test_mark_as_delivered(): void
    {
        $message = WctpMessage::factory()->sent()->create([
            'status' => 'sent',
            'delivered_at' => null,
        ]);

        $beforeTime = now();
        $message->markAsDelivered();
        $afterTime = now();

        $message->refresh();
        $this->assertEquals('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
        $this->assertTrue($message->delivered_at->greaterThanOrEqualTo($beforeTime->subSecond()));
    }

    public function test_mark_as_failed(): void
    {
        $message = WctpMessage::factory()->sent()->create([
            'status' => 'sent',
            'failed_at' => null,
        ]);

        $beforeTime = now();
        $message->markAsFailed('Delivery failed');
        $afterTime = now();

        $message->refresh();
        $this->assertEquals('failed', $message->status);
        $this->assertNotNull($message->failed_at);
        $this->assertTrue($message->failed_at->greaterThanOrEqualTo($beforeTime->subSecond()));
    }

    public function test_fillable_attributes(): void
    {
        $expectedFillable = [
            'enterprise_host_id',
            'to',
            'from',
            'message',
            'wctp_message_id',
            'twilio_sid',
            'direction',
            'status',
            'error_message',
            'delivered_at',
            'failed_at',
            'submitted_at',
            'processed_at',
            'reply_with',
            'parent_message_id',
        ];

        $message = new WctpMessage;
        $this->assertEquals($expectedFillable, $message->getFillable());
    }

    public function test_casts(): void
    {
        $message = WctpMessage::factory()->create([
            'delivered_at' => '2023-01-01 12:10:00',
            'failed_at' => '2023-01-01 12:15:00',
        ]);

        // Test datetime casts
        $this->assertInstanceOf(Carbon::class, $message->delivered_at);
        $this->assertInstanceOf(Carbon::class, $message->failed_at);
    }

    public function test_table_name(): void
    {
        $message = new WctpMessage;
        $this->assertEquals('wctp_messages', $message->getTable());
    }

    public function test_factory_states(): void
    {
        // Test pending state
        $pending = WctpMessage::factory()->pending()->create();
        $this->assertEquals('pending', $pending->status);
        $this->assertNull($pending->twilio_sid);
        $this->assertNull($pending->delivered_at);
        $this->assertNull($pending->failed_at);

        // Test sent state
        $sent = WctpMessage::factory()->sent()->create();
        $this->assertEquals('sent', $sent->status);
        $this->assertNotNull($sent->twilio_sid);

        // Test delivered state
        $delivered = WctpMessage::factory()->delivered()->create();
        $this->assertEquals('delivered', $delivered->status);
        $this->assertNotNull($delivered->delivered_at);

        // Test failed state
        $failed = WctpMessage::factory()->failed()->create();
        $this->assertEquals('failed', $failed->status);
        $this->assertNotNull($failed->failed_at);
    }

    public function test_factory_inbound_direction(): void
    {
        $message = WctpMessage::factory()->inbound()->create();

        $this->assertEquals('inbound', $message->direction);
        $this->assertEquals('+15551234567', $message->to); // Our number
        $this->assertNotEquals('+15551234567', $message->from); // Customer number
    }
}
