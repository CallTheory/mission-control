<?php

namespace App\Jobs\Concerns;

trait InteractsWithFaxSpool
{
    /**
     * Determine whether any OTHER .fs file in the tosend directory still points at the
     * given .cap payload. Used to avoid deleting a fanned-out .cap (one document sent to
     * several numbers via multiple .fs files) before every recipient has been processed.
     */
    protected function capStillReferenced(string $toSendDir, string $capfile, string $currentFsFileName): bool
    {
        foreach (glob($toSendDir.'*.fs') ?: [] as $otherFs) {
            if (basename($otherFs) === $currentFsFileName) {
                continue;
            }

            $contents = @file_get_contents($otherFs);

            if ($contents !== false && str_contains($contents, $capfile)) {
                return true;
            }
        }

        return false;
    }
}
