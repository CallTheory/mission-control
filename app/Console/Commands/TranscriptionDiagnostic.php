<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class TranscriptionDiagnostic extends Command
{
    protected $signature = 'transcription:diagnose {isCallID?}';

    protected $description = 'Diagnose transcription pipeline health and optionally test with a specific call ID';

    public function handle(): int
    {
        $this->info('=== Transcription Pipeline Diagnostic ===');
        $this->newLine();

        $healthy = true;

        // 1. Feature flag
        $this->info('[1] Feature Flag');
        if (Helpers::isSystemFeatureEnabled('transcription')) {
            $this->line('    Status: ENABLED');
        } else {
            $this->error('    Status: DISABLED - transcription feature flag is not enabled');
            $this->line('    Check: Enable via System > Enabled Features, or verify feature-flags/transcription.flag exists in storage');
            $healthy = false;
        }
        $this->newLine();

        // 2. Whisper binary
        $this->info('[2] Whisper Binary');
        $whisperRoot = config('whisper.project_root', '/opt/whisper.cpp');
        $whisperBin = "{$whisperRoot}/build/bin/whisper-cli";
        if (file_exists($whisperBin)) {
            $this->line("    Binary: {$whisperBin} EXISTS");
            if (is_executable($whisperBin)) {
                $this->line('    Executable: YES');
            } else {
                $this->error('    Executable: NO - binary exists but is not executable');
                $healthy = false;
            }
        } else {
            $this->error("    Binary: {$whisperBin} NOT FOUND");
            $healthy = false;
        }
        $this->newLine();

        // 3. Whisper model
        $this->info('[3] Whisper Model');
        $whisperModel = config('whisper.model', 'ggml-base.en.bin');
        $modelPath = "{$whisperRoot}/models/{$whisperModel}";
        if (file_exists($modelPath)) {
            $size = round(filesize($modelPath) / 1024 / 1024, 1);
            $this->line("    Model: {$modelPath} EXISTS ({$size} MB)");
        } else {
            $this->error("    Model: {$modelPath} NOT FOUND");
            $healthy = false;
        }
        $this->newLine();

        // 4. Sox binary
        $this->info('[4] Sox Binary');
        $soxProcess = new Process(['which', 'sox']);
        $soxProcess->run();
        if ($soxProcess->isSuccessful()) {
            $this->line('    Sox: '.trim($soxProcess->getOutput()));
        } else {
            $this->error('    Sox: NOT FOUND - required for recording conversion');
            $healthy = false;
        }
        $this->newLine();

        // 5. Redis connectivity
        $this->info('[5] Redis');
        try {
            Redis::ping();
            $this->line('    Connection: OK');
        } catch (\Exception $e) {
            $this->error("    Connection: FAILED - {$e->getMessage()}");
            $healthy = false;
        }
        $this->newLine();

        // 6. Storage directories
        $this->info('[6] Storage Directories');
        $recordingPath = storage_path('app/recordings');
        $transcriptionPath = storage_path('app/transcriptions');
        foreach (['recordings' => $recordingPath, 'transcriptions' => $transcriptionPath] as $name => $path) {
            if (is_dir($path)) {
                $this->line("    {$name}: {$path} EXISTS (writable: ".(is_writable($path) ? 'YES' : 'NO').')');
                if (! is_writable($path)) {
                    $healthy = false;
                }
            } else {
                $this->warn("    {$name}: {$path} MISSING (will be created on first use)");
            }
        }
        $this->newLine();

        // 7. Queue / Horizon status
        $this->info('[7] Queue Configuration');
        $this->line('    Transcription queue: transcriptions');
        $this->line('    Sox queue: sox');
        $this->line('    Rate limit: 1 per minute (WithoutOverlapping + RateLimited)');
        $pendingTranscriptions = null;
        try {
            $pendingTranscriptions = Redis::llen('queues:transcriptions');
            $this->line("    Pending transcription jobs: {$pendingTranscriptions}");
            $pendingSox = Redis::llen('queues:sox');
            $this->line("    Pending sox jobs: {$pendingSox}");
        } catch (\Exception $e) {
            $this->warn("    Could not check queue depth: {$e->getMessage()}");
        }
        $this->newLine();

        // 8. Whisper smoke test (if binary + model exist)
        $this->info('[8] Whisper Smoke Test');
        if (file_exists($whisperBin) && file_exists($modelPath)) {
            $this->line('    Running whisper-cli --help to verify binary works...');
            $testProcess = new Process([$whisperBin, '--help']);
            $testProcess->setTimeout(10);
            $testProcess->run();
            if ($testProcess->getExitCode() === 0 || str_contains($testProcess->getErrorOutput(), 'usage')) {
                $this->line('    Smoke test: PASSED');
            } else {
                $this->error('    Smoke test: FAILED');
                $this->line('    Exit code: '.$testProcess->getExitCode());
                $this->line('    stderr: '.substr($testProcess->getErrorOutput(), 0, 500));
                $healthy = false;
            }
        } else {
            $this->warn('    Skipped - binary or model not found');
        }
        $this->newLine();

        // 9. Optional: test with a specific call ID
        $isCallID = $this->argument('isCallID');
        if ($isCallID) {
            $this->info("[9] Call Test: {$isCallID}");

            // Check if transcription already exists in Redis
            $existingJson = Redis::get("{$isCallID}.json");
            if ($existingJson) {
                $this->line('    Existing transcription in Redis: YES ('.strlen($existingJson).' bytes)');
            } else {
                $this->line('    Existing transcription in Redis: NO');
            }

            // Check if WAV exists in Redis
            $existingWav = Redis::get("{$isCallID}.wav");
            if ($existingWav) {
                $this->line('    Existing WAV in Redis: YES ('.strlen($existingWav).' bytes)');
            } else {
                $this->line('    Existing WAV in Redis: NO');
            }

            // Try to fetch recording data
            $this->line('    Fetching recording data from source...');
            try {
                $call = new Call(['ISCallId' => $isCallID]);
                $recordings = $call->recordingData();
                $count = count($recordings);
                $this->line("    Recordings found: {$count}");

                if ($count === 0) {
                    $this->error('    No recordings returned - this call may not have recordings or the data source may be unreachable');
                } else {
                    $totalSize = 0;
                    foreach ($recordings as $recording) {
                        $size = strlen($recording->Data ?? '');
                        $totalSize += $size;
                        $this->line("      File {$recording->fileID}: {$size} bytes");
                    }
                    $this->line("    Total recording data: ".round($totalSize / 1024, 1).' KB');

                    if ($totalSize === 0) {
                        $this->error('    WARNING: Recording data is empty (0 bytes)');
                    }
                }
            } catch (\Exception $e) {
                $this->error("    Failed to fetch recordings: {$e->getMessage()}");
                $healthy = false;
            }
            $this->newLine();
        }

        // Summary
        $this->newLine();
        if ($healthy) {
            $this->info('RESULT: All checks passed. Pipeline appears healthy.');
            if (! $isCallID) {
                $this->line('Tip: Run with a call ID to test end-to-end: php artisan transcription:diagnose <isCallID>');
            }
        } else {
            $this->error('RESULT: One or more checks failed. See above for details.');
        }

        return $healthy ? self::SUCCESS : self::FAILURE;
    }
}
