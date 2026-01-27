<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\MoveFailedFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CheckPendingFaxesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableCloudFaxing();

        // Force array cache to avoid Redis dependency (ShouldBeUnique lock check)
        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'fax_buildup_notification_email' => 'test@example.com',
            'fax_failure_notification_email' => 'test@example.com',
        ]);
    }

    public function test_it_exits_successfully_when_no_pending_faxes(): void
    {
        $this->artisan('isfax:check-pending')
            ->assertExitCode(0);
    }

    public function test_it_exits_successfully_when_feature_disabled(): void
    {
        $this->disableCloudFaxing();

        PendingFax::create($this->makePendingFaxAttrs());

        $this->artisan('isfax:check-pending')
            ->assertExitCode(0);
    }

    public function test_it_increments_poll_attempts(): void
    {
        Bus::fake();
        Mail::fake();

        // Create a pending fax that will timeout (> 120 attempts)
        $pendingFax = PendingFax::create(array_merge($this->makePendingFaxAttrs(), [
            'poll_attempts' => 120,
        ]));

        $this->artisan('isfax:check-pending')
            ->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertEquals(121, $pendingFax->poll_attempts);
    }

    public function test_it_fails_fax_after_120_poll_attempts(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create(array_merge($this->makePendingFaxAttrs(), [
            'poll_attempts' => 120,
        ]));

        $this->artisan('isfax:check-pending')
            ->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertEquals('failed', $pendingFax->delivery_status);
        $this->assertNotNull($pendingFax->resolved_at);

        Bus::assertDispatched(MoveFailedFaxFiles::class);
        Mail::assertQueued(FaxFailAlert::class);
    }

    public function test_it_does_not_fail_fax_before_121_attempts(): void
    {
        Bus::fake();
        Mail::fake();

        // At exactly 119, it should still try to poll (not timeout)
        // We can't actually test the API call without mocking HTTP,
        // so we just verify it doesn't auto-fail at 119
        $pendingFax = PendingFax::create(array_merge($this->makePendingFaxAttrs(), [
            'poll_attempts' => 119,
        ]));

        // This will fail because there's no actual API to call,
        // but the point is it doesn't mark as failed due to timeout
        $this->artisan('isfax:check-pending')
            ->assertExitCode(0);

        $pendingFax->refresh();
        // It incremented to 120 but didn't timeout (threshold is > 120)
        $this->assertEquals(120, $pendingFax->poll_attempts);
        // Status unchanged because API call threw exception (no real API)
        $this->assertEquals('pending', $pendingFax->delivery_status);
    }

    private function makePendingFaxAttrs(array $overrides = []): array
    {
        return array_merge([
            'api_fax_id' => 'test-uuid-'.uniqid(),
            'fax_provider' => 'mfax',
            'job_id' => rand(1, 99999),
            'fs_file_name' => 'test.fs',
            'cap_file' => 'test.cap',
            'filename' => 'test.cap',
            'phone' => '5551234567',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now(),
        ], $overrides);
    }

    private function enableCloudFaxing(): void
    {
        Storage::makeDirectory('feature-flags');
        Storage::put('feature-flags/cloud-faxing.flag', encrypt('cloud-faxing'));
    }

    private function disableCloudFaxing(): void
    {
        Storage::delete('feature-flags/cloud-faxing.flag');
    }
}
