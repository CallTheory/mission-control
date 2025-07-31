<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MoveSuccessfulFaxFiles implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
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
        $this->jobID = $faxFsDetails['jobID'];
        $this->capfile = $faxFsDetails['capfile'];
        $this->filename = $faxFsDetails['filename'];
        $this->phone = $faxFsDetails['phone'];
        $this->status = $faxFsDetails['status'];
        $this->fsFileName = $faxFsDetails['fsFileName'];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $fsFile = storage_path("app/{$this->fax_provider}/tosend/{$this->fsFileName}");

        if(config('app.switch_engine') == 'infinity')
        {
            $capFile = storage_path("app/{$this->fax_provider}/messages/{$this->capfile}");
            $sentCapFile = storage_path("app/{$this->fax_provider}/messages/{$this->capfile}");
        }
        else {
            $capFile = storage_path("app/{$this->fax_provider}/tosend/{$this->capfile}");
            $sentCapFile = storage_path("app/{$this->fax_provider}/sent/{$this->capfile}");
        }

        $sentFsFile = storage_path("app/{$this->fax_provider}/sent/{$this->fsFileName}");


        $capFileContents = file_get_contents($capFile);
        $fsFileContents = file_get_contents($fsFile);
        $fsFileContents = str_replace('tosend', 'sent', $fsFileContents);
        $fsFileContents = str_replace('$fax_status1 '.$this->status, '$fax_status2 0', $fsFileContents);
        file_put_contents($sentFsFile, $fsFileContents);
        file_put_contents($sentCapFile, $capFileContents);
        unlink($fsFile);
        unlink($capFile);
    }

    public function uniqueId()
    {
        return $this->jobID;
    }
}
