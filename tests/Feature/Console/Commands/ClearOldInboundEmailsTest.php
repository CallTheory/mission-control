<?php

namespace Tests\Feature\Console\Commands;

use App\Models\InboundEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClearOldInboundEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    /** @test */
    public function it_removes_old_inbound_emails_and_their_attachments()
    {
        // Create old and new inbound emails
        $oldEmail = InboundEmail::factory()->create([
            'created_at' => now()->subDays(config('utilities.inbound-email.days_to_keep') + 1),
        ]);

        $newEmail = InboundEmail::factory()->create([
            'created_at' => now()->subDays(config('utilities.inbound-email.days_to_keep') - 1),
        ]);

        // Create attachment directories
        Storage::makeDirectory("inbound-email/{$oldEmail->id}");
        Storage::makeDirectory("inbound-email/{$newEmail->id}");

        $this->artisan('app:clear-old-inbound-emails')
            ->expectsOutput('Removing inbound emails older than '.config('utilities.inbound-email.days_to_keep').' days')
            ->assertExitCode(0);

        // Assert old email and its attachments are deleted
        $this->assertDatabaseMissing('inbound_emails', ['id' => $oldEmail->id]);
        $this->assertFalse(Storage::exists("inbound-email/{$oldEmail->id}"));

        // Assert new email and its attachments are preserved
        $this->assertDatabaseHas('inbound_emails', ['id' => $newEmail->id]);
        $this->assertTrue(Storage::exists("inbound-email/{$newEmail->id}"));
    }

    /** @test */
    public function it_removes_orphaned_attachment_directories()
    {
        // Create an attachment directory for a non-existent email
        $nonExistentEmailId = 999;
        Storage::makeDirectory("inbound-email/{$nonExistentEmailId}");

        $this->artisan('app:clear-old-inbound-emails')
            ->expectsOutput('Removing inbound emails older than '.config('utilities.inbound-email.days_to_keep').' days')
            ->assertExitCode(0);

        // Assert orphaned directory is removed
        $this->assertFalse(Storage::exists("inbound-email/{$nonExistentEmailId}"));
    }

    /** @test */
    public function it_handles_empty_inbound_emails_table()
    {
        $this->artisan('app:clear-old-inbound-emails')
            ->expectsOutput('Removing inbound emails older than '.config('utilities.inbound-email.days_to_keep').' days')
            ->assertExitCode(0);

        // Command should complete successfully with no errors
        $this->assertTrue(true);
    }
}
