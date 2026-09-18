<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxRingCentral;
use App\Models\PendingFax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Read and maintain the fax spool directories that Amtelco's fax service and Mission
 * Control hand files back and forth through.
 *
 * Both fax utility pages used to scandir() these folders inline and render bare
 * filenames, which told you a file was stuck but nothing about whose fax it was or how
 * long it had been there — and offered no way to clear it without an SSH session. This
 * centralises the listing (with account attribution) and the delete/clear operations, so
 * the UI and the dashboard builder agree on what is in the spool.
 */
class FaxSpool
{
    /**
     * @var array<int, string>
     */
    public const PROVIDERS = ['mfax', 'ringcentral'];

    /**
     * Snapshot key => directory name under storage/app/{provider}/.
     *
     * @var array<string, string>
     */
    public const FOLDERS = [
        'files_to_send' => 'tosend',
        'files_in_sent' => 'sent',
        'files_in_fail' => 'fail',
        'files_in_pre' => 'preproc',
    ];

    /**
     * Never list or delete these — they are repository scaffolding, not fax traffic.
     *
     * @var array<int, string>
     */
    private const IGNORED = ['.', '..', '.gitignore'];

    private ?FaxAccountLookup $accounts;

    public function __construct(?FaxAccountLookup $accounts = null)
    {
        $this->accounts = $accounts;
    }

    /**
     * Every folder's contents plus counts, in the shape the fax dashboards render.
     *
     * @return array<string, mixed>
     */
    public function snapshot(string $provider): array
    {
        $this->assertProvider($provider);

        $snapshot = [];

        foreach (array_keys(self::FOLDERS) as $key) {
            $files = $this->files($provider, self::FOLDERS[$key]);

            $snapshot[$key] = $files;
            $snapshot["{$key}_count"] = count($files);
        }

        return $snapshot;
    }

    /**
     * Describe each file in one spool folder: what it is, how long it has been sitting
     * there, and which Intelligent Series account it belongs to.
     *
     * @return array<int, array<string, mixed>>
     */
    public function files(string $provider, string $folder): array
    {
        $path = $this->path($provider, $folder);

        if (! is_dir($path)) {
            return [];
        }

        $names = array_values(array_diff(scandir($path) ?: [], self::IGNORED));

        $descriptors = [];
        $jobIds = [];

        foreach ($names as $name) {
            $full = $path.$name;

            if (! is_file($full)) {
                continue;
            }

            $jobId = Str::endsWith($name, '.fs') ? $this->jobIdFromFsFile($full) : null;

            if ($jobId !== null) {
                $jobIds[] = $jobId;
            }

            $descriptors[] = [
                'name' => $name,
                'type' => $this->describeType($name),
                'size' => @filesize($full) ?: 0,
                'modified_at' => Carbon::createFromTimestamp(@filemtime($full) ?: 0)->toIso8601String(),
                'job_id' => $jobId,
                'account' => null,
            ];
        }

        return $this->attributeAccounts($provider, $folder, $descriptors, $jobIds);
    }

    /**
     * Delete one file from a spool folder.
     *
     * Returns false when the file is already gone, which is the common case for a phantom
     * that the fax service cleaned up between the page rendering and the click.
     */
    public function delete(string $provider, string $folder, string $filename, ?string $actor = null): bool
    {
        $target = $this->resolveFile($provider, $folder, $filename);

        if ($target === null) {
            return false;
        }

        if (! @unlink($target)) {
            throw new RuntimeException("Unable to delete {$filename}.");
        }

        // Deleting a spool file is unrecoverable and bypasses the fax service, so it is
        // always recorded with who did it.
        Log::warning('Fax spool file deleted', [
            'provider' => $provider,
            'folder' => $folder,
            'file' => basename($target),
            'actor' => $actor,
        ]);

        $this->abandonTracking($provider, basename($target), $actor);

        return true;
    }

    /**
     * Empty a spool folder. Returns the number of files removed.
     */
    public function clear(string $provider, string $folder, ?string $actor = null): int
    {
        $path = $this->path($provider, $folder);

        if (! is_dir($path)) {
            return 0;
        }

        $deleted = 0;

        foreach (array_diff(scandir($path) ?: [], self::IGNORED) as $name) {
            if (! is_file($path.$name)) {
                continue;
            }

            try {
                if ($this->delete($provider, $folder, $name, $actor)) {
                    $deleted++;
                }
            } catch (Throwable $e) {
                Log::error("Fax spool clear failed for {$name}: ".$e->getMessage());
            }
        }

        Log::warning('Fax spool folder cleared', [
            'provider' => $provider,
            'folder' => $folder,
            'deleted' => $deleted,
            'actor' => $actor,
        ]);

        return $deleted;
    }

    /**
     * Coerce a folder listing into descriptor shape.
     *
     * Listings used to be plain filename strings. A snapshot built by the previous
     * version of isfax:build-ringcentral-dashboard stays in Redis for up to its TTL after
     * a deploy, so the page has to render one without blowing up — and anything else that
     * kept a listing around gets the same treatment.
     *
     * @param  array<int, mixed>  $files
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeListing(array $files): array
    {
        return array_values(array_map(function ($file): array {
            if (is_array($file)) {
                return $file + [
                    'name' => '',
                    'type' => 'other',
                    'size' => 0,
                    'modified_at' => null,
                    'job_id' => null,
                    'account' => null,
                ];
            }

            $name = (string) $file;

            return [
                'name' => $name,
                'type' => match (true) {
                    Str::endsWith($name, '.cap') => 'cap',
                    Str::endsWith($name, '.fs') => 'fs',
                    default => 'other',
                },
                'size' => 0,
                'modified_at' => null,
                'job_id' => null,
                'account' => null,
            ];
        }, $files));
    }

    public function path(string $provider, string $folder): string
    {
        $this->assertProvider($provider);

        if (! in_array($folder, self::FOLDERS, true)) {
            throw new InvalidArgumentException("Unknown fax spool folder [{$folder}].");
        }

        return storage_path("app/{$provider}/{$folder}/");
    }

    /**
     * Human label for a spool folder, used as the section heading in the UI.
     */
    public static function folderLabel(string $folder): string
    {
        return match ($folder) {
            'tosend' => 'To Send',
            'sent' => 'Sent',
            'fail' => 'Failed',
            'preproc' => 'Pre-Proc',
            default => ucfirst($folder),
        };
    }

    /**
     * Resolve a user-supplied filename to a real path inside the spool folder, or null
     * when it does not exist.
     *
     * Filenames reach us from the browser, so they are reduced to a basename, matched
     * against a conservative pattern, and finally confirmed by realpath to sit directly
     * inside the intended directory — a symlink or a traversal attempt resolves outside
     * it and is refused.
     */
    private function resolveFile(string $provider, string $folder, string $filename): ?string
    {
        $path = $this->path($provider, $folder);
        $name = basename(trim($filename));

        if ($name === '' || in_array($name, self::IGNORED, true) || str_starts_with($name, '.')) {
            throw new InvalidArgumentException('Refusing to operate on that filename.');
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name)) {
            throw new InvalidArgumentException("Unexpected characters in filename [{$filename}].");
        }

        $real = realpath($path.$name);
        $realDir = realpath($path);

        if ($real === false || $realDir === false) {
            return null;
        }

        if (dirname($real) !== $realDir || ! is_file($real) || is_link($path.$name)) {
            throw new InvalidArgumentException("Refusing to operate on [{$filename}].");
        }

        return $real;
    }

    /**
     * Stop tracking a fax whose spool file has been deleted by hand.
     *
     * Two things would otherwise keep chasing a file that no longer exists: the pending
     * fax record, which the poller would re-check until it timed out, and the send job's
     * unique lock, which would refuse to re-dispatch that .fs name if the fax service
     * later dropped a fresh file with it.
     */
    private function abandonTracking(string $provider, string $filename, ?string $actor): void
    {
        if (! Str::endsWith($filename, '.fs')) {
            return;
        }

        PendingFax::pending()
            ->where('fax_provider', $provider)
            ->where('fs_file_name', $filename)
            ->get()
            ->each(function (PendingFax $fax) use ($actor) {
                $fax->update([
                    'delivery_status' => 'failed',
                    'resolved_at' => Carbon::now(),
                ]);

                Log::warning("PendingFax #{$fax->id} abandoned: spool file deleted".($actor ? " by {$actor}" : ''));
            });

        $this->releaseUniqueLock($provider, $filename);
    }

    /**
     * Force-release the ShouldBeUnique lock keyed on this .fs name. The key format is
     * Illuminate\Bus\UniqueLock::getKey(): the job class, then the job's uniqueId().
     */
    private function releaseUniqueLock(string $provider, string $filename): void
    {
        $jobClass = $provider === 'ringcentral' ? SendFaxRingCentral::class : SendFaxJob::class;

        try {
            Cache::lock("laravel_unique_job:{$jobClass}:{$filename}")->forceRelease();
        } catch (Throwable $e) {
            Log::warning("Unable to release fax job lock for {$filename}: ".$e->getMessage());
        }
    }

    /**
     * Label each file with the account it belongs to.
     *
     * A .fs carries the job id, so the account comes straight from the lookup. A .cap is
     * the payload with no metadata of its own, so it borrows the account from the
     * pending_faxes row that references it, or from a sibling .fs in the same folder that
     * points at it — which is exactly how a fanned-out .cap is shared.
     *
     * @param  array<int, array<string, mixed>>  $descriptors
     * @param  array<int, int>  $jobIds
     * @return array<int, array<string, mixed>>
     */
    private function attributeAccounts(string $provider, string $folder, array $descriptors, array $jobIds): array
    {
        if ($descriptors === []) {
            return [];
        }

        $accounts = [];

        if ($jobIds !== []) {
            try {
                // Memoized: a snapshot walks four folders, and resolving the data source
                // afresh for each would be four needless queries.
                $accounts = ($this->accounts ??= FaxAccountLookup::make())->forJobIds($jobIds);
            } catch (Throwable $e) {
                Log::warning('FaxSpool: unable to resolve spool accounts: '.$e->getMessage());
            }
        }

        $capNames = array_values(array_map(
            fn (array $d) => $d['name'],
            array_filter($descriptors, fn (array $d) => $d['type'] === 'cap')
        ));

        $capAccounts = $capNames === [] ? [] : $this->accountsForCapFiles($provider, $capNames);

        // A .fs sitting beside a .cap tells us the .cap's account even when no
        // pending_faxes row exists (the fax was never submitted).
        $path = $this->path($provider, $folder);

        foreach ($descriptors as $descriptor) {
            if ($descriptor['type'] !== 'fs' || $descriptor['job_id'] === null) {
                continue;
            }

            $account = $accounts[$descriptor['job_id']] ?? null;

            if ($account === null) {
                continue;
            }

            foreach ($this->capFilesReferencedBy($path.$descriptor['name'], $capNames) as $capName) {
                $capAccounts[$capName] ??= $account;
            }
        }

        return array_map(function (array $descriptor) use ($accounts, $capAccounts) {
            $account = $descriptor['type'] === 'cap'
                ? ($capAccounts[$descriptor['name']] ?? null)
                : ($accounts[$descriptor['job_id']] ?? null);

            $descriptor['account'] = $account === null
                ? null
                : trim($account['number'].(blank($account['name']) ? '' : " — {$account['name']}"));

            return $descriptor;
        }, $descriptors);
    }

    /**
     * @param  array<int, string>  $capNames
     * @return array<string, array{number: string, name: string}>
     */
    private function accountsForCapFiles(string $provider, array $capNames): array
    {
        return PendingFax::query()
            ->where('fax_provider', $provider)
            ->whereIn('cap_file', $capNames)
            ->whereNotNull('client_number')
            ->latest('id')
            ->get(['cap_file', 'client_number', 'client_name'])
            ->reduce(function (array $carry, PendingFax $fax) {
                $carry[$fax->cap_file] ??= [
                    'number' => (string) $fax->client_number,
                    'name' => (string) ($fax->client_name ?? ''),
                ];

                return $carry;
            }, []);
    }

    /**
     * Which of the given .cap names this .fs file points at.
     *
     * @param  array<int, string>  $capNames
     * @return array<int, string>
     */
    private function capFilesReferencedBy(string $fsPath, array $capNames): array
    {
        $contents = @file_get_contents($fsPath);

        if ($contents === false) {
            return [];
        }

        return array_values(array_filter($capNames, fn (string $cap) => str_contains($contents, $cap)));
    }

    /**
     * Pull the Intelligent Series job id out of a .fs file's `$var_def DATA5` line.
     *
     * Deliberately narrow: the full .fs parsers in the isfax:process commands own the
     * format, this only needs the one field that identifies the account.
     */
    private function jobIdFromFsFile(string $path): ?int
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        if (! preg_match('/\$var_def\s+DATA5\s+"?(\d+)"?/', $contents, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }

    private function describeType(string $name): string
    {
        return match (true) {
            Str::endsWith($name, '.cap') => 'cap',
            Str::endsWith($name, '.fs') => 'fs',
            default => 'other',
        };
    }

    private function assertProvider(string $provider): void
    {
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException("Unknown fax provider [{$provider}].");
        }
    }
}
