<?php

namespace App\Jobs;

use App\Models\Stats\Helpers;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class WhisperCppTranscriptionJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    public string $whisper_root = '/opt/whisper.cpp';

    public string $whisper_model = 'ggml-base.en.bin';

    public string $whisper_command_params = '';

    public int $timeout = 1800; // 30 minutes, its unique, and could be large file downloads

    public int $tries = 60; // Allow many attempts since middleware releases (rate limiting, overlapping) each burn an attempt

    public int $maxExceptions = 3; // Only count actual failures, not middleware releases

    public string $filename;

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('transcriptions'))->releaseAfter(60),
            (new RateLimited('transcriptions')),
        ];
    }

    /**
     * Create a new job instance.
     */
    public function __construct($filename)
    {
        $this->queue = 'transcriptions';
        $this->filename = $filename;

        if (config('whisper.project_root')) {
            $this->whisper_root = config('whisper.project_root');
        }

        if (config('whisper.model')) {
            $this->whisper_model = config('whisper.model');
        }

        if (config('whisper.command_params')) {
            $this->whisper_command_params = config('whisper.command_params');
        }

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Storage::put("recordings/{$this->filename}", Redis::get($this->filename));
        } catch (Exception $e) {
            Log::error("Failed to save recording from cache for processing: {$this->filename}");

            return;
        }

        $recording_storage = storage_path('app/recordings');
        $transcription_storage = storage_path('app/transcriptions');
        $filepath = "{$recording_storage}/{$this->filename}";
        $json_filename = basename($this->filename, '.wav');

        if (! Helpers::isSystemFeatureEnabled('transcription')) {
            Log::warning("Transcription feature flag is disabled, skipping transcription for {$this->filename}");

            return;
        }

        if (file_exists($filepath)) {
            $filesize = filesize($filepath);
            Log::info("Transcription starting for {$this->filename} ({$filesize} bytes)");

            $transcribe = "{$this->whisper_root}/build/bin/whisper-cli {$this->whisper_command_params} -m {$this->whisper_root}/models/{$this->whisper_model} -f {$recording_storage}/{$this->filename} -ojf -of {$transcription_storage}/{$json_filename}";
            Log::info("Transcribing {$this->filename}: {$transcribe}");
            try {
                $result = Process::timeout($this->timeout)->run($transcribe)->throw();
                $jsonContent = Storage::get("transcriptions/{$json_filename}.json");
                if ($jsonContent) {
                    Redis::setEx("{$json_filename}.json", 86400, $jsonContent);
                    Log::info("Transcription for {$this->filename} completed successfully (".strlen($jsonContent).' bytes)');
                } else {
                    Log::error("Transcription for {$this->filename} produced no output file at transcriptions/{$json_filename}.json");
                }
            } catch (Exception $e) {
                Log::error("Failed to transcribe {$this->filename}: {$e->getMessage()}");
            }
        } else {
            Log::error("Failed to find recording {$this->filename} at {$filepath}");
        }

        try {
            Storage::delete("transcriptions/{$json_filename}.json");
        } catch (Exception $e) {
        }

        try {
            Storage::delete("recordings/{$this->filename}");
        } catch (Exception $e) {
            Log::error("Failed to delete recording {$this->filename}");
        }
    }

    public function uniqueId(): string
    {
        return $this->filename;
    }
}
