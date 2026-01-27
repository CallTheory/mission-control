<?php

namespace Tests\Unit\Models;

use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingFaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_pending_fax(): void
    {
        $pendingFax = PendingFax::create([
            'api_fax_id' => 'test-uuid-123',
            'fax_provider' => 'mfax',
            'job_id' => 100,
            'fs_file_name' => 'test.fs',
            'cap_file' => 'test.cap',
            'filename' => 'test.cap',
            'phone' => '5551234567',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->assertDatabaseHas('pending_faxes', [
            'id' => $pendingFax->id,
            'api_fax_id' => 'test-uuid-123',
            'fax_provider' => 'mfax',
            'job_id' => 100,
            'delivery_status' => 'pending',
        ]);
    }

    public function test_delivery_status_defaults_to_pending(): void
    {
        $pendingFax = PendingFax::create([
            'api_fax_id' => 'test-uuid-456',
            'fax_provider' => 'ringcentral',
            'job_id' => 200,
            'fs_file_name' => 'test2.fs',
            'cap_file' => 'test2.cap',
            'filename' => 'test2.cap',
            'phone' => '5559876543',
            'original_status' => 'queued',
            'submitted_at' => now(),
        ]);

        $pendingFax->refresh();
        $this->assertEquals('pending', $pendingFax->delivery_status);
    }

    public function test_poll_attempts_defaults_to_zero(): void
    {
        $pendingFax = PendingFax::create([
            'api_fax_id' => 'test-uuid-789',
            'fax_provider' => 'mfax',
            'job_id' => 300,
            'fs_file_name' => 'test3.fs',
            'cap_file' => 'test3.cap',
            'filename' => 'test3.cap',
            'phone' => '5551112222',
            'original_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $pendingFax->refresh();
        $this->assertEquals(0, $pendingFax->poll_attempts);
    }

    public function test_submitted_at_is_cast_to_datetime(): void
    {
        $pendingFax = PendingFax::create([
            'api_fax_id' => 'test-uuid-cast',
            'fax_provider' => 'mfax',
            'job_id' => 400,
            'fs_file_name' => 'test4.fs',
            'cap_file' => 'test4.cap',
            'filename' => 'test4.cap',
            'phone' => '5553334444',
            'original_status' => 'pending',
            'submitted_at' => '2026-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $pendingFax->submitted_at);
    }

    public function test_resolved_at_is_cast_to_datetime(): void
    {
        $pendingFax = PendingFax::create([
            'api_fax_id' => 'test-uuid-resolved',
            'fax_provider' => 'mfax',
            'job_id' => 500,
            'fs_file_name' => 'test5.fs',
            'cap_file' => 'test5.cap',
            'filename' => 'test5.cap',
            'phone' => '5555556666',
            'original_status' => 'pending',
            'delivery_status' => 'success',
            'submitted_at' => now(),
            'resolved_at' => '2026-01-15 11:00:00',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $pendingFax->resolved_at);
    }
}
