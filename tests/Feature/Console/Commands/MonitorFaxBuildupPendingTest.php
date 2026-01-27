<?php

namespace Tests\Feature\Console\Commands;

use App\Mail\FaxBuildupAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonitorFaxBuildupPendingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::makeDirectory('feature-flags');
        Storage::put('feature-flags/cloud-faxing.flag', encrypt('cloud-faxing'));

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');

        DataSource::create([
            'fax_buildup_notification_email' => 'test@example.com',
        ]);

        // Ensure fax directories exist and are clean for scandir calls
        $providers = ['mfax', 'ringcentral'];
        foreach ($providers as $provider) {
            foreach (['tosend', 'sent', 'fail'] as $dir) {
                $path = storage_path("app/{$provider}/{$dir}");
                if (! is_dir($path)) {
                    mkdir($path, 0755, true);
                }
                // Remove any leftover .fs/.cap files that could trigger false alerts
                foreach (glob("{$path}/*.{fs,cap}", GLOB_BRACE) as $file) {
                    unlink($file);
                }
            }
        }
    }

    public function test_it_alerts_when_pending_faxes_older_than_30_minutes(): void
    {
        Mail::fake();

        PendingFax::create([
            'api_fax_id' => 'stale-uuid',
            'fax_provider' => 'mfax',
            'job_id' => 999,
            'fs_file_name' => 'stale.fs',
            'cap_file' => 'stale.cap',
            'filename' => 'stale.cap',
            'phone' => '5551234567',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now()->subMinutes(45),
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ]);

        $this->artisan('isfax:monitor', ['fax_provider' => 'mfax'])
            ->assertExitCode(0);

        Mail::assertQueued(FaxBuildupAlert::class);
    }

    public function test_it_does_not_alert_for_recent_pending_faxes(): void
    {
        Mail::fake();

        PendingFax::create([
            'api_fax_id' => 'fresh-uuid',
            'fax_provider' => 'mfax',
            'job_id' => 888,
            'fs_file_name' => 'fresh.fs',
            'cap_file' => 'fresh.cap',
            'filename' => 'fresh.cap',
            'phone' => '5559876543',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->artisan('isfax:monitor', ['fax_provider' => 'mfax'])
            ->assertExitCode(0);

        Mail::assertNotQueued(FaxBuildupAlert::class);
    }
}
