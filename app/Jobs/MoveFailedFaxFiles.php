<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithFaxSpool;
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
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($faxFsDetails, $fax_provider = 'mfax')
    {
        $this->fax_provider = $fax_provider;
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
        $toSendDir = storage_path("app/{$this->fax_provider}/tosend/");
        $fsFile = $toSendDir.$this->fsFileName;
        $failedFsFile = storage_path("app/{$this->fax_provider}/fail/{$this->fsFileName}");
        $failedCapFile = storage_path("app/{$this->fax_provider}/fail/{$this->capfile}");

        if (config('app.switch_engine') == 'infinity') {
            $capFile = storage_path("app/{$this->fax_provider}/messages/{$this->capfile}");
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
        return $this->fsFileName;
    }
}
