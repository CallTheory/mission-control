<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\DataSource;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Resolves the Intelligent Series account (client) a fax job belongs to.
 *
 * mFax has always known this: SendFaxJob looked the account up and pushed it to Documo
 * as a tag, which is how faxes were identified in that interface. RingCentral has no
 * equivalent tag concept, so instead of the account number living only in the provider,
 * it is resolved here and stored on the pending_faxes row — which makes it available to
 * both providers' dashboards, the spool file listings, and the alert emails.
 *
 * The lookup is the same join SendFaxJob used: faxJobs → cltClients on the job id from
 * the .fs file's $var_def DATA5.
 */
class FaxAccountLookup
{
    /**
     * Account details for a job id never change, so cache generously. The window only
     * exists so a client rename eventually shows up.
     */
    private const CACHE_TTL_SECONDS = 3600;

    /**
     * Remember misses too, for much less time, so a .fs referencing a job id that isn't
     * in the IS database doesn't re-query on every dashboard rebuild.
     */
    private const MISS_CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly DataSource $datasource) {}

    public static function make(?DataSource $datasource = null): self
    {
        return new self($datasource ?? DataSource::firstOrFail());
    }

    /**
     * @return array{number: string, name: string}|null
     */
    public function forJobId(?int $jobId): ?array
    {
        if ($jobId === null || $jobId <= 0) {
            return null;
        }

        return $this->forJobIds([$jobId])[$jobId] ?? null;
    }

    /**
     * Resolve many job ids in a single round trip — the dashboard has to label every
     * file sitting in the spool folders, and one query per file would be pointless load
     * on the IS database.
     *
     * @param  array<int, int|string|null>  $jobIds
     * @return array<int, array{number: string, name: string}>
     */
    public function forJobIds(array $jobIds): array
    {
        $jobIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => is_numeric($id) ? (int) $id : null,
            $jobIds
        ), fn (?int $id) => $id !== null && $id > 0)));

        if ($jobIds === []) {
            return [];
        }

        $resolved = [];
        $missing = [];

        foreach ($jobIds as $jobId) {
            $cached = $this->readCache($jobId);

            if ($cached === null) {
                $missing[] = $jobId;
            } elseif ($cached !== []) {
                $resolved[$jobId] = $cached;
            }
            // A cached empty array is a remembered miss: skip it without querying.
        }

        if ($missing === []) {
            return $resolved;
        }

        foreach ($this->query($missing) as $jobId => $account) {
            $resolved[$jobId] = $account;
        }

        // Cache the outcome for every id we asked about, hits and misses alike.
        foreach ($missing as $jobId) {
            $this->writeCache($jobId, $resolved[$jobId] ?? []);
        }

        return $resolved;
    }

    /**
     * @param  array<int, int>  $jobIds
     * @return array<int, array{number: string, name: string}>
     */
    private function query(array $jobIds): array
    {
        $this->configureConnection();

        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));

        $sql = 'select f.jobid as JobId, c.ClientNumber as ClientNumber, c.ClientName as ClientName '
            ."from faxJobs f left join cltClients c on f.cltID = c.cltId where f.jobid in ({$placeholders})";

        try {
            $rows = DB::connection('intelligent')->select($sql, $jobIds);
        } catch (Throwable $e) {
            if (App::environment('local')) {
                throw new Exception('Unable to query fax account details: '.$e->getMessage(), 0, $e);
            }

            // A fax must still go out when the IS database is unreachable; it just goes
            // out unlabelled.
            Log::warning('FaxAccountLookup: account lookup failed: '.$e->getMessage());

            return [];
        }

        $resolved = [];

        foreach ($rows as $row) {
            $number = $row->ClientNumber ?? null;

            if ($number === null || $number === '') {
                continue;
            }

            $resolved[(int) $row->JobId] = [
                'number' => (string) $number,
                'name' => (string) ($row->ClientName ?? ''),
            ];
        }

        return $resolved;
    }

    private function configureConnection(): void
    {
        Config::set('database.connections.intelligent', [
            'driver' => 'sqlsrv',
            'host' => $this->datasource->is_db_host,
            'port' => $this->datasource->is_db_port,
            'database' => $this->datasource->is_db_data,
            'username' => $this->datasource->is_db_user,
            'password' => $this->datasource->is_db_pass,
            'encrypt' => true,
            'trust_server_certificate' => true,
        ]);
    }

    /**
     * @return array{number: string, name: string}|array{}|null null when not cached
     */
    private function readCache(int $jobId): ?array
    {
        $payload = Redis::get($this->cacheKey($jobId));

        if ($payload === null) {
            return null;
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array{number: string, name: string}|array{}  $account
     */
    private function writeCache(int $jobId, array $account): void
    {
        Redis::setEx(
            $this->cacheKey($jobId),
            $account === [] ? self::MISS_CACHE_TTL_SECONDS : self::CACHE_TTL_SECONDS,
            json_encode($account, JSON_UNESCAPED_SLASHES)
        );
    }

    private function cacheKey(int $jobId): string
    {
        return "cloud-faxing:account:{$jobId}";
    }
}
