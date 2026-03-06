<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessCallRecording implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240;

    public string $isCallID;

    /**
     * Create a new job instance.
     */
    public function __construct(string $isCallID)
    {
        $this->isCallID = $isCallID;
        $this->queue = 'sox';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessCallRecording starting for call {$this->isCallID}");
        $exitCode = Artisan::call('recording:convert', ['isCallID' => $this->isCallID]);
        if ($exitCode !== 0) {
            Log::error("Recording conversion failed for call {$this->isCallID} (exit code: {$exitCode})");
        }
        Storage::deleteDirectory("recordings/{$this->isCallID}/");
        Storage::delete("recordings/{$this->isCallID}.wav");
    }

    public function uniqueId(): string
    {
        return $this->isCallID;
    }
}
