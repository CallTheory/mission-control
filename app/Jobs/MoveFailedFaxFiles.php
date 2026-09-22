<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithFaxSpool;
use App\Services\Faxing\FaxLockKey;
use App\Services\Faxing\FaxSpool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MoveFailedFaxFiles implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithFaxSpool, InteractsWithQueue, Queueable, SerializesModels;

    public int $jobID;

    public string $capfile;

    public string $filename;

    public string $phone;

    public string $status;

    public string $fsFileName;

    public string $fax_provider;

    /**
     * Which spool source's folders to move the files within.
     *
     * Nullable with a null default so a payload serialized before sources existed still
     * unserializes — PHP applies a declared default only for properties the payload does
     * not carry, and a non-nullable typed property would fatal here. Null resolves to the
     * provider-named legacy source, which is where such a payload came from.
     */
    public ?string $spoolSourceKey = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($faxFsDetails, $fax_provider = 'mfax', ?string $spoolSourceKey = null)
    {
        $this->fax_provider = $fax_provider;
        // The array form is how every existing dispatch site already passes context, so
        // a caller that has the source but not the extra argument still works.
        $this->spoolSourceKey = $spoolSourceKey ?? ($faxFsDetails['source_key'] ?? null);
        // basename() the filenames from .fs contents so they can't escape the spool dir.
        $this->capfile = basename($faxFsDetails['capfile']);
        $this->jobID = $faxFsDetails['jobID'];
        $this->filename = basename($faxFsDetails['filename']);
        $this->phone = $faxFsDetails['phone'];
        $this->status = $faxFsDetails['status'];
        $this->fsFileName = basename($faxFsDetails['fsFileName']);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $toSendDir = $this->spoolPath('tosend');
        $fsFile = $toSendDir.$this->fsFileName;
        $failedFsFile = $this->spoolPath('fail').$this->fsFileName;
        $failedCapFile = $this->spoolPath('fail').$this->capfile;

        if (config('app.switch_engine') == 'infinity') {
            $capFile = $this->spoolPath('messages').$this->capfile;
        } else {
            $capFile = $toSendDir.$this->capfile;
        }

        if (file_exists($fsFile)) {
            $fsFileContents = file_get_contents($fsFile);
            $fsFileContents = str_replace('tosend', 'fail', $fsFileContents);
            $fsFileContents = str_replace('$fax_status1 '.$this->status, '$fax_status2 261', $fsFileContents);
            file_put_contents($failedFsFile, $fsFileContents);
            unlink($fsFile);
        }

        // Only remove the source .cap once no other fanned-out .fs still references it.
        if (file_exists($capFile)) {
            file_put_contents($failedCapFile, file_get_contents($capFile));

            if (! $this->capStillReferenced($toSendDir, $this->capfile, $this->fsFileName)) {
                unlink($capFile);
            }
        }
    }

    public function uniqueId(): string
    {
        return FaxLockKey::for($this->spoolSourceKey, $this->fax_provider, $this->fsFileName);
    }

    /**
     * The spool source these files live in, defaulting to the provider-named legacy one.
     */
    public function sourceKey(): string
    {
        return $this->spoolSourceKey ?? $this->fax_provider;
    }

    private function spoolPath(string $folder): string
    {
        return (new FaxSpool)->path($this->fax_provider, $folder, $this->sourceKey());
    }
}
