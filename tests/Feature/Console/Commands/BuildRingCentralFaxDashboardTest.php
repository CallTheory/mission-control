<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BuildRingCentralFaxDashboardTest extends TestCase
{
    use RefreshDatabase;

    private array $dirs = ['tosend', 'sent', 'fail', 'preproc'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableCloudFaxing();
        config(['cache.default' => 'array']);

        foreach ($this->dirs as $dir) {
            $path = storage_path("app/ringcentral/{$dir}/");
            if (! is_dir($path)) {
                mkdir($path, 0775, true);
            }
            // Start from a clean directory so folder counts are deterministic.
            foreach (array_diff(scandir($path), ['.', '..', '.gitignore']) as $file) {
                @unlink($path.$file);
            }
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            $path = storage_path("app/ringcentral/{$dir}/");
            foreach (array_diff(scandir($path), ['.', '..', '.gitignore']) as $file) {
                @unlink($path.$file);
            }
        }

        $this->disableCloudFaxing();

        parent::tearDown();
    }

    public function test_it_caches_a_snapshot_of_the_spool_folders(): void
    {
        DataSource::create([]); // No RingCentral credentials configured.

        file_put_contents(storage_path('app/ringcentral/tosend/IS20.fs'), 'x');
        file_put_contents(storage_path('app/ringcentral/tosend/IS20.cap'), 'x');
        file_put_contents(storage_path('app/ringcentral/sent/IS19.fs'), 'x');

        $captured = null;
        Redis::shouldReceive('setEx')
            ->once()
            ->withArgs(function ($key, $ttl, $json) use (&$captured) {
                $captured = compact('key', 'ttl', 'json');

                return $key === BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY;
            });

        $this->artisan('isfax:build-ringcentral-dashboard')->assertExitCode(0);

        $data = json_decode($captured['json'], true);

        $this->assertSame(2, $data['files_to_send_count']);
        $this->assertSame(1, $data['files_in_sent_count']);
        $this->assertSame(0, $data['files_in_fail_count']);
        $this->assertContains('IS20.fs', $data['files_to_send']);
        // No RingCentral client configured → page should render the API-unavailable notice.
        $this->assertFalse($data['failed_faxes']);
        $this->assertNotNull($data['generated_at']);
    }

    public function test_it_does_nothing_when_feature_disabled(): void
    {
        $this->disableCloudFaxing();
        DataSource::create([]);

        Redis::shouldReceive('setEx')->never();

        $this->artisan('isfax:build-ringcentral-dashboard')->assertExitCode(0);
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
