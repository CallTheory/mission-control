<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxRingCentral;
use App\Models\PendingFax;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Puts a failed fax back in front of the fax service.
 *
 * A fax that never reached the provider has no provider-side id, so the existing Resend —
 * which asks the provider to send a message it already holds — cannot touch it. The only
 * thing that still exists is the .fs/.cap pair sitting in fail/, which is exactly what
 * the sender consumes. So retrying means moving that pair back to tosend/ and letting the
 * ordinary scan pick it up; nothing bespoke runs, and the fax goes out through whatever
 * routing is current rather than whatever it was when it failed.
 */
class FaxRetry
{
    public function __construct(private readonly FaxSpool $spool) {}

    /**
     * @return array<int, string> the files moved back
     *
     * @throws RuntimeException when there is nothing to retry
     */
    public function retry(PendingFax $fax, ?string $actor = null): array
    {
        $provider = (string) $fax->fax_provider;
        $source = (string) ($fax->spool_source_key ?: $provider);
        $filesystem = $this->spool->filesystem($provider, $source);

        if ($filesystem->read('fail', $fax->fs_file_name) === null) {
            throw new RuntimeException(
                "{$fax->fs_file_name} is no longer in the failed folder, so there is nothing to resend. "
                .'It may already have been retried, or cleared.'
            );
        }

        // Refuse when the payload has gone. Intelligent Series fans one .cap out to
        // several .fs files, so a sibling's move can legitimately remove it — putting
        // this .fs back without it would recreate the orphan that fails and emails on
        // every scan, which is the loop this whole thing exists to stop.
        if ($fax->cap_file !== '' && $filesystem->read('fail', $fax->cap_file) === null) {
            throw new RuntimeException(
                "The payload {$fax->cap_file} is no longer in the failed folder, so this fax cannot be "
                .'sent again from here. It has to be re-sent from Intelligent Series.'
            );
        }

        // Any lock left over from the attempt that failed would silently swallow the
        // re-dispatch — the job would simply never run, with nothing logged.
        $this->releaseLocks($provider, $source, $fax->fs_file_name);

        $moved = [];

        // The payload first: without it the .fs is a phantom the sender will quarantine.
        if ($fax->cap_file !== '' && $filesystem->read('fail', $fax->cap_file) !== null) {
            if ($filesystem->move('fail', $fax->cap_file, 'tosend', $fax->cap_file)) {
                $moved[] = $fax->cap_file;
            }
        }

        if (! $filesystem->move('fail', $fax->fs_file_name, 'tosend', $fax->fs_file_name)) {
            throw new RuntimeException("Could not move {$fax->fs_file_name} back for another attempt.");
        }

        $moved[] = $fax->fs_file_name;

        // Stamp it so the same failure is not offered again while the retry is in flight;
        // the scan creates a fresh row for the new attempt.
        $fax->forceFill(['retried_at' => now()])->save();

        Log::warning('Fax returned to the send folder for another attempt', [
            'fs_file_name' => $fax->fs_file_name,
            'provider' => $provider,
            'source' => $source,
            'account' => $fax->accountLabel(),
            'actor' => $actor,
        ]);

        return $moved;
    }

    /**
     * Clear every unique-job lock keyed on this .fs name.
     *
     * Intelligent Series reuses these filenames, so a lock stranded by an interrupted
     * worker outlives the fax that took it and suppresses the next one with the same
     * name.
     */
    private function releaseLocks(string $provider, string $source, string $fsFileName): void
    {
        $uniqueId = FaxLockKey::for($source, $provider, $fsFileName);

        $jobs = [
            $provider === 'ringcentral' ? SendFaxRingCentral::class : SendFaxJob::class,
            MoveSuccessfulFaxFiles::class,
            MoveFailedFaxFiles::class,
        ];

        foreach ($jobs as $job) {
            try {
                Cache::lock("laravel_unique_job:{$job}:{$uniqueId}")->forceRelease();
            } catch (Throwable $e) {
                Log::warning("Unable to release {$job} lock for {$fsFileName}: ".$e->getMessage());
            }
        }
    }
}
