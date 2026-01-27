<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WhisperCppTranscriptionJob;
use App\Models\Stats\Calls\Call;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ConvertRecordingToMp3 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recording:convert-mp3 {isCallID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Converts recording to MP3 format for email attachments';

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $isCallID = $this->argument('isCallID');

        // Check if MP3 already exists in Redis
        if (Redis::exists("{$isCallID}.mp3")) {
            $this->info('MP3 already exists in cache.');

            return CommandStatus::SUCCESS;
        }

        // Check if WAV exists in Redis, if not, fetch and convert
        if (! Redis::exists("{$isCallID}.wav")) {
            $result = $this->convertToWav($isCallID);
            if ($result !== CommandStatus::SUCCESS) {
                return $result;
            }
        }

        // Get WAV data from Redis
        $wavData = Redis::get("{$isCallID}.wav");
        if (! $wavData) {
            $this->error('WAV data not found in cache.');

            return CommandStatus::FAILURE;
        }

        // Write WAV to temp file for lame conversion
        $tempWavPath = storage_path("app/recordings/{$isCallID}_temp.wav");
        $tempMp3Path = storage_path("app/recordings/{$isCallID}_temp.mp3");

        // Ensure directory exists
        Storage::makeDirectory('recordings');

        file_put_contents($tempWavPath, $wavData);

        try {
            // Convert WAV to MP3 using lame
            $process = new Process([
                'lame',
                '-V', '2',
                '--quiet',
                $tempWavPath,
                $tempMp3Path,
            ]);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            // Read MP3 and store in Redis with 24h TTL
            if (file_exists($tempMp3Path)) {
                $mp3Data = file_get_contents($tempMp3Path);
                Redis::setEx("{$isCallID}.mp3", 86400, $mp3Data);
                $this->info('MP3 created and cached successfully.');
            }
        } catch (Exception $e) {
            $this->error('Failed to convert to MP3: '.$e->getMessage());

            return CommandStatus::FAILURE;
        } finally {
            // Cleanup temp files
            if (file_exists($tempWavPath)) {
                unlink($tempWavPath);
            }
            if (file_exists($tempMp3Path)) {
                unlink($tempMp3Path);
            }
        }

        return CommandStatus::SUCCESS;
    }

    /**
     * Convert recording to WAV format (reuses logic from ConvertRecording).
     */
    private function convertToWav(string $isCallID): int
    {
        $call = new Call(['ISCallId' => $isCallID]);

        $recordings = $call->recordingData();
        $recordingPath = [];
        foreach ($recordings as $recording) {
            $recordingPath[$recording->fileID] = "recordings/{$isCallID}/{$recording->fileID}.wav";
            Storage::put($recordingPath[$recording->fileID], $recording->Data);
        }

        if (count($recordingPath) === 0) {
            Storage::deleteDirectory("recordings/{$isCallID}");
            $this->error('No recordings found for call.');

            return CommandStatus::FAILURE;
        }

        $files = Storage::files("recordings/{$isCallID}");

        $fullPathFiles = [];

        foreach ($files as $file) {
            $this->line("Found {$file}");
            $fullPathFiles[] = storage_path("app/{$file}");
        }

        try {
            $process = new Process(array_merge(
                ['sox'],
                $fullPathFiles,
                ['-r', 16000],
                ['-b', 16],
                ['-c', 1],
                [storage_path("app/recordings/{$isCallID}.wav")])
            );
            $process->run();
        } catch (Exception $e) {
            Storage::deleteDirectory("recordings/{$isCallID}");
            Storage::delete("recordings/{$isCallID}.wav");
            $this->error('Sox conversion failed: '.$e->getMessage());

            return CommandStatus::FAILURE;
        }

        if (! $process->isSuccessful()) {
            Storage::deleteDirectory("recordings/{$isCallID}");
            Storage::delete("recordings/{$isCallID}.wav");
            throw new ProcessFailedException($process);
        }

        $this->line('Sox Output (empty is successful): '.$process->getOutput());

        if (Storage::exists("recordings/{$isCallID}.wav")) {
            Redis::setEx("{$isCallID}.wav", 86400, Storage::get("recordings/{$isCallID}.wav"));
            WhisperCppTranscriptionJob::dispatch("{$isCallID}.wav");
        }

        Storage::deleteDirectory("recordings/{$isCallID}");

        return CommandStatus::SUCCESS;
    }
}
