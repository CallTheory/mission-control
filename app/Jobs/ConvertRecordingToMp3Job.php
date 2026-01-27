<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ConvertRecordingToMp3Job implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $isCallID;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 300;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(string $isCallID)
    {
        $this->isCallID = $isCallID;
        $this->queue = 'sox';
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return $this->isCallID;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Artisan::call('recording:convert-mp3', ['isCallID' => $this->isCallID]);
            Log::info("MP3 conversion completed for call {$this->isCallID}");
        } catch (\Exception $e) {
            Log::error("MP3 conversion failed for call {$this->isCallID}: ".$e->getMessage());
            throw $e;
        }
    }
}
