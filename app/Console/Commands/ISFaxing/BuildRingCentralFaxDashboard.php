<?php

namespace App\Console\Commands\ISFaxing;

use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxDeliveryWebhooks;
use App\Services\Faxing\RingCentralClient;
use App\Services\Faxing\RingCentralExtension;
use App\Services\Faxing\RingCentralSenderPool;
use App\Services\Faxing\RingCentralThrottle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Throwable;

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

        // Provider-level only. The spool folders moved to a per-source snapshot
        // (isfax:build-dashboards): several Intelligent Series servers have separate
        // directories, and one shared key would show whichever was written last as
        // though it were all of them. What is left here genuinely is shared — a
        // RingCentral callback carries no notion of which IS server produced the fax.
        $snapshot = [
            'failed_faxes' => $this->fetchFailedFaxes($datasource),
            'webhook_last_received_at' => FaxDeliveryWebhooks::lastReceivedAt('ringcentral'),
            'generated_at' => now()->toIso8601String(),
        ];

        Redis::setEx(self::DASHBOARD_CACHE_KEY, self::CACHE_TTL_SECONDS, json_encode($snapshot, JSON_UNESCAPED_SLASHES));

        return CommandStatus::SUCCESS;
    }

    /**
     * Fetch the recent fax list from RingCentral, from every sending extension.
     *
     * Each extension has its own message store, so with a sender pool the faxes sent
     * through additional extensions are only visible by asking each one. Every record is
     * tagged with the sender it came from (mc_sender), which is how a resend from the page
     * knows which extension to authenticate as.
     *
     * On any failure we keep that extension's previously cached records (so a transient
     * API hiccup doesn't blank the table); only if nothing was fetched and there is no
     * prior data do we return false, which the page renders as an API-error notice.
     *
     * @return array<int, mixed>|false
     */
    private function fetchFailedFaxes(DataSource $datasource): array|false
    {
        $extensions = app(RingCentralSenderPool::class)->extensions($datasource);

        if ($extensions === [] || ! RingCentralClient::forExtension($datasource, $extensions[0])->configured()) {
            return false;
        }

        $previous = $this->previousFailedFaxes();
        $records = [];
        $anySucceeded = false;

        foreach ($extensions as $extension) {
            $fetched = $this->fetchFor($datasource, $extension);

            if ($fetched !== null) {
                $anySucceeded = true;
                $records = [...$records, ...$fetched];

                continue;
            }

            if (is_array($previous)) {
                $records = [...$records, ...array_values(array_filter(
                    $previous,
                    fn ($record) => is_array($record) && ($record['mc_sender'] ?? RingCentralExtension::PRIMARY) === $extension->key
                ))];
            }
        }

        if (! $anySucceeded && $records === [] && $previous === false) {
            return false;
        }

        usort($records, fn ($a, $b) => strcmp((string) ($b['creationTime'] ?? ''), (string) ($a['creationTime'] ?? '')));

        return $this->withAccounts($records);
    }

    /**
     * @return array<int, mixed>|null null when the fetch failed
     */
    private function fetchFor(DataSource $datasource, RingCentralExtension $extension): ?array
    {
        $client = RingCentralClient::forExtension($datasource, $extension);

        try {
            // Shares the cached access token with the send jobs rather than performing its
            // own JWT login every minute.
            $resp = $client->platform()->get('/restapi/v1.0/account/~/extension/~/message-store', [
                'messageType' => ['Fax'],
                'dateFrom' => now()->subDays(self::LOOKBACK_DAYS)->toIso8601String(),
                'perPage' => 100,
            ]);

            return array_map(
                fn ($record) => is_array($record) ? $record + ['mc_sender' => $extension->key] : $record,
                $resp->jsonArray()['records'] ?? []
            );
        } catch (Throwable $e) {
            if (RingCentralThrottle::isUnauthorized($e)) {
                $client->forgetToken();
            }

            Log::error("BuildRingCentralFaxDashboard: failed to fetch fax list for {$extension->key}: ".$e->getMessage());

            return null;
        }
    }

    /**
     * Attach the Intelligent Series account to each RingCentral record.
     *
     * RingCentral has no tag concept, so the account is recovered from the pending_faxes
     * row written when the fax was submitted, matched on the provider's message id. A
     * record with no match — an inbound fax, or one sent outside Mission Control — simply
     * has no account.
     *
     * @param  array<int, mixed>  $records
     * @return array<int, mixed>
     */
    private function withAccounts(array $records): array
    {
        $ids = array_values(array_filter(array_map(
            fn ($record) => isset($record['id']) ? (string) $record['id'] : null,
            $records
        )));

        if ($ids === []) {
            return $records;
        }

        $accounts = PendingFax::query()
            ->where('fax_provider', 'ringcentral')
            ->whereIn('api_fax_id', $ids)
            ->whereNotNull('client_number')
            ->get(['api_fax_id', 'client_number', 'client_name'])
            ->mapWithKeys(fn (PendingFax $fax) => [$fax->api_fax_id => $fax->accountLabel()])
            ->all();

        return array_map(function ($record) use ($accounts) {
            $record['account'] = $accounts[(string) ($record['id'] ?? '')] ?? null;

            return $record;
        }, $records);
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
