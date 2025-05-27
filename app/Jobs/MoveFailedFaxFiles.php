<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MoveFailedFaxFiles implements ShouldQueue, ShouldBeUnique, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $this->capfile = $faxFsDetails['capfile'];
        $this->jobID = $faxFsDetails['jobID'];
        $this->filename = $faxFsDetails['filename'];
        $this->phone = $faxFsDetails['phone'];
        $this->status = $faxFsDetails['status'];
        $this->fsFileName = $faxFsDetails['fsFileName'];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $fsFile = storage_path("app/{$this->fax_provider}/tosend/{$this->fsFileName}");
        $capFile = storage_path("app/{$this->fax_provider}/tosend/{$this->capfile}");

        $failedFsFile = storage_path("app/{$this->fax_provider}/fail/{$this->fsFileName}");
        $failedCapFile = storage_path("app/{$this->fax_provider}/fail/{$this->capfile}");

        $capFileContents = file_get_contents($capFile);
        $fsFileContents = file_get_contents($fsFile);
        $fsFileContents = str_replace('tosend', 'fail', $fsFileContents);
        $fsFileContents = str_replace('$fax_status1 '.$this->status, '$fax_status2 261', $fsFileContents);
        file_put_contents($failedFsFile, $fsFileContents);
        file_put_contents($failedCapFile, $capFileContents);
        unlink($fsFile);
        unlink($capFile);
    }

    public function uniqueId()
    {
        return $this->jobID;
    }
}
