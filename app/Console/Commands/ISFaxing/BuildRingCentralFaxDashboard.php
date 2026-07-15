<?php

namespace App\Console\Commands\ISFaxing;

use App\Models\DataSource;
use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RingCentral\SDK\SDK as RingCentralSDK;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * Builds the single shared snapshot the RingCentral fax status page renders from.
 *
 * The page used to query the RingCentral API and scandir() the spool folders inside the
 * Livewire request (per user, on a staggered 60s poll), which made it slow to appear and
 * inconsistent between users. Here we do that work once per minute and cache one snapshot
 * in Redis so every viewer reads the same instant result.
 */
class BuildRingCentralFaxDashboard extends Command
{
    public const DASHBOARD_CACHE_KEY = 'cloud-faxing:ring-central:dashboard';

    /**
     * How long the snapshot stays valid. Comfortably longer than the 1-minute schedule so
     * a single failed/slow run never blanks the page.
     */
    private const CACHE_TTL_SECONDS = 180;

    /**
     * How far back to pull faxes for the status dashboard. RingCentral's message-store
     * endpoint defaults to roughly a 24h window when dateFrom is omitted, which made the
     * page look empty whenever no fax was sent in the last day. An explicit window fixes it.
     */
    private const LOOKBACK_DAYS = 7;

    protected $signature = 'isfax:build-ringcentral-dashboard';

    protected $description = 'Build the cached RingCentral fax status dashboard snapshot';

    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::SUCCESS;
        }

        $datasource = DataSource::first();

        if ($datasource === null) {
            return CommandStatus::SUCCESS;
        }

        $snapshot = $this->scanFolders();
        $snapshot['failed_faxes'] = $this->fetchFailedFaxes($datasource);
        $snapshot['generated_at'] = now()->toIso8601String();

        Redis::setEx(self::DASHBOARD_CACHE_KEY, self::CACHE_TTL_SECONDS, json_encode($snapshot, JSON_UNESCAPED_SLASHES));

        return CommandStatus::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function scanFolders(): array
    {
        $folders = [
            'files_to_send' => 'tosend',
            'files_in_sent' => 'sent',
            'files_in_fail' => 'fail',
            'files_in_pre' => 'preproc',
        ];

        $snapshot = [];

        foreach ($folders as $key => $dir) {
            $path = storage_path("app/ringcentral/{$dir}/");
            $files = is_dir($path)
                ? array_values(array_diff(scandir($path), ['.', '..', '.gitignore']))
                : [];

            $snapshot[$key] = $files;
            $snapshot["{$key}_count"] = count($files);
        }

        return $snapshot;
    }

    /**
     * Fetch the recent fax list from RingCentral. On any failure we keep the previously
     * cached list (so a transient API hiccup doesn't blank the table); only if there is no
     * prior data do we return false, which the page renders as an API-error notice.
     *
     * @return array<int, mixed>|false
     */
    private function fetchFailedFaxes(DataSource $datasource): array|false
    {
        if (empty($datasource->ringcentral_client_id)) {
            return false;
        }

        try {
            $rcsdk = new RingCentralSDK(
                $datasource->ringcentral_client_id,
                decrypt($datasource->ringcentral_client_secret),
                $datasource->ringcentral_api_endpoint
            );
            $platform = $rcsdk->platform();
            $platform->login(['jwt' => decrypt($datasource->ringcentral_jwt_token)]);

            $resp = $platform->get('/restapi/v1.0/account/~/extension/~/message-store', [
                'messageType' => ['Fax'],
                'dateFrom' => now()->subDays(self::LOOKBACK_DAYS)->toIso8601String(),
                'perPage' => 100,
            ]);

            return $resp->jsonArray()['records'] ?? [];
        } catch (Exception $e) {
            Log::error('BuildRingCentralFaxDashboard: failed to fetch fax list: '.$e->getMessage());

            return $this->previousFailedFaxes();
        }
    }

    /**
     * @return array<int, mixed>|false
     */
    private function previousFailedFaxes(): array|false
    {
        $existing = Redis::get(self::DASHBOARD_CACHE_KEY);

        if ($existing === null) {
            return false;
        }

        try {
            $decoded = json_decode($existing, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return $decoded['failed_faxes'] ?? false;
    }
}
