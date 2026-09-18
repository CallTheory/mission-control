<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\MoveFailedFaxFiles;
use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

class CheckPendingFaxesTest extends TestCase
{
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableCloudFaxing();

        // Force array cache to avoid Redis dependency (ShouldBeUnique lock check)
        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        // Deterministic polling windows, independent of whatever the deployment is tuned to.
        config([
            'services.fax.poll_grace_seconds' => 120,
            'services.fax.poll_interval_seconds' => 120,
            'services.fax.pending_timeout_seconds' => 7200,
            'services.fax.ringcentral.poll_batch_size' => 15,
        ]);

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

    /**
     * A freshly submitted fax is left alone: a provider webhook normally resolves it
     * inside the grace window, and polling it immediately would spend API quota the
     * outbound faxes need.
     */
    public function test_it_leaves_a_freshly_submitted_fax_unpolled(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'submitted_at' => now()->subSeconds(5),
        ]));

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertNull($pendingFax->last_polled_at);
        $this->assertSame(0, $pendingFax->poll_attempts);
        $this->assertSame('pending', $pendingFax->delivery_status);
    }

    public function test_it_polls_a_fax_once_the_grace_window_has_passed(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'submitted_at' => now()->subMinutes(10),
        ]));

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertNotNull($pendingFax->last_polled_at);
        $this->assertSame(1, $pendingFax->poll_attempts);
        // There is no provider to answer, so the status is untouched.
        $this->assertSame('pending', $pendingFax->delivery_status);
    }

    /**
     * The old implementation re-checked every pending fax every minute. Spacing the
     * checks is what stops the poller from starving the sender of API quota.
     */
    public function test_it_does_not_repoll_within_the_poll_interval(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'submitted_at' => now()->subMinutes(10),
            'last_polled_at' => now()->subSeconds(30),
            'poll_attempts' => 3,
        ]));

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertSame(3, $pendingFax->poll_attempts);
    }

    public function test_it_polls_again_once_the_interval_has_elapsed(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'submitted_at' => now()->subMinutes(10),
            'last_polled_at' => now()->subMinutes(5),
            'poll_attempts' => 3,
        ]));

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertSame(4, $pendingFax->poll_attempts);
    }

    public function test_it_polls_no_more_than_the_batch_size_per_run(): void
    {
        Bus::fake();
        Mail::fake();

        config(['services.fax.ringcentral.poll_batch_size' => 2]);

        foreach (range(1, 5) as $i) {
            PendingFax::create($this->makePendingFaxAttrs([
                'submitted_at' => now()->subMinutes(10 + $i),
            ]));
        }

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $this->assertSame(2, PendingFax::where('poll_attempts', '>', 0)->count());
    }

    /**
     * The timeout is measured in time, not in poll attempts — now that polling is spaced
     * and batched, an attempt count no longer corresponds to any particular duration.
     */
    public function test_it_fails_a_fax_pending_past_the_timeout(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'submitted_at' => now()->subSeconds(7300),
        ]));

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertSame('failed', $pendingFax->delivery_status);
        $this->assertNotNull($pendingFax->resolved_at);

        Bus::assertDispatched(MoveFailedFaxFiles::class);
        Mail::assertQueued(FaxFailAlert::class);
    }

    public function test_it_does_not_fail_a_fax_inside_the_timeout(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs([
            'submitted_at' => now()->subSeconds(3600),
        ]));

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $pendingFax->refresh();
        $this->assertSame('pending', $pendingFax->delivery_status);
        $this->assertNull($pendingFax->resolved_at);
    }

    /**
     * A fax whose submitted_at was never recorded still has to time out, or it would
     * stay pending forever and keep the buildup monitor complaining.
     */
    public function test_it_fails_an_old_fax_with_no_submitted_at(): void
    {
        Bus::fake();
        Mail::fake();

        $pendingFax = PendingFax::create($this->makePendingFaxAttrs(['submitted_at' => null]));
        $pendingFax->forceFill(['created_at' => now()->subSeconds(7300)])->save();

        $this->artisan('isfax:check-pending')->assertExitCode(0);

        $this->assertSame('failed', $pendingFax->refresh()->delivery_status);
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
        $this->enableSystemFeature('cloud-faxing');
    }

    private function disableCloudFaxing(): void
    {
        $this->disableSystemFeature('cloud-faxing');
    }
}
