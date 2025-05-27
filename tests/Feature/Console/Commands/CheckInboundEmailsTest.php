<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\InboundRuleMatch;
use App\Models\InboundEmail;
use App\Models\Stats\Helpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CheckInboundEmailsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_processes_unprocessed_inbound_emails_when_feature_is_enabled()
    {
        Queue::fake();
        
        // Enable the inbound-email feature
        Helpers::enableSystemFeature('inbound-email');

        // Create unprocessed inbound emails
        $emails = InboundEmail::factory()->count(3)->create([
            'processed_at' => null,
            'ignored_at' => null
        ]);

        $this->artisan('inbound-emails:check')
            ->expectsOutput('')
            ->assertExitCode(0);

        // Assert that jobs were dispatched for each email
        foreach ($emails as $email) {
            Queue::assertPushed(InboundRuleMatch::class, function ($job) use ($email) {
                return $job->email->id === $email->id;
            });
        }
    }

    /** @test */
    public function it_does_not_process_emails_when_feature_is_disabled()
    {
        Queue::fake();
        
        // Disable the inbound-email feature
        Helpers::disableSystemFeature('inbound-email');

        // Create unprocessed inbound emails
        InboundEmail::factory()->count(3)->create([
            'processed_at' => null,
            'ignored_at' => null
        ]);

        $this->artisan('inbound-emails:check')
            ->expectsOutput('')
            ->assertExitCode(0);

        // Assert that no jobs were dispatched
        Queue::assertNothingPushed();
    }

    /** @test */
    public function it_does_not_process_already_processed_emails()
    {
        Queue::fake();
        
        Helpers::enableSystemFeature('inbound-email');

        // Create processed and unprocessed emails
        InboundEmail::factory()->create([
            'processed_at' => now(),
            'ignored_at' => null
        ]);

        $unprocessedEmail = InboundEmail::factory()->create([
            'processed_at' => null,
            'ignored_at' => null
        ]);

        $this->artisan('inbound-emails:check')
            ->expectsOutput('')
            ->assertExitCode(0);

        // Assert that only one job was dispatched for the unprocessed email
        Queue::assertPushed(InboundRuleMatch::class, function ($job) use ($unprocessedEmail) {
            return $job->email->id === $unprocessedEmail->id;
        });
    }
} 