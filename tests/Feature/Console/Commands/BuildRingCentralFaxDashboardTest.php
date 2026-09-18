<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Models\DataSource;
use App\Models\PendingFax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use Tests\Traits\InteractsWithFeatureFlags;

class BuildRingCentralFaxDashboardTest extends TestCase
{
    use InteractsWithFeatureFlags;
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

        file_put_contents(storage_path('app/ringcentral/tosend/IS20.fs'), '$var_def DATA5 "4242"'."\r\n".'$var_def DATA6 "IS20.cap"');
        file_put_contents(storage_path('app/ringcentral/tosend/IS20.cap'), 'x');
        file_put_contents(storage_path('app/ringcentral/sent/IS19.fs'), 'x');

        // The .cap has no metadata of its own; it takes its account from the pending fax
        // row that references it.
        PendingFax::create([
            'api_fax_id' => 'msg-1',
            'fax_provider' => 'ringcentral',
            'job_id' => 4242,
            'fs_file_name' => 'IS20.fs',
            'cap_file' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '5551234567',
            'client_number' => '9001',
            'client_name' => 'Acme Clinic',
            'original_status' => 'pending',
            'delivery_status' => 'pending',
            'submitted_at' => now(),
        ]);

        $captured = null;
        Redis::shouldReceive('get')->andReturn(null);
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

        $names = array_column($data['files_to_send'], 'name');
        $this->assertContains('IS20.fs', $names);
        $this->assertContains('IS20.cap', $names);

        $descriptors = array_column($data['files_to_send'], null, 'name');
        $this->assertSame('fs', $descriptors['IS20.fs']['type']);
        $this->assertSame(4242, $descriptors['IS20.fs']['job_id']);
        $this->assertSame('cap', $descriptors['IS20.cap']['type']);
        $this->assertSame('9001 — Acme Clinic', $descriptors['IS20.cap']['account']);

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
        $this->enableSystemFeature('cloud-faxing');
    }

    private function disableCloudFaxing(): void
    {
        $this->disableSystemFeature('cloud-faxing');
    }
}
