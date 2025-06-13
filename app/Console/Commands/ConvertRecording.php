<?php

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

class ConvertRecording extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recording:convert {isCallID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Joins and converts wav files into appropriate playback';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $isCallID = $this->argument('isCallID');

        $call = new Call(['ISCallId' => $isCallID]);

        $recordings = $call->recordingData();
        $recordingPath = [];
        foreach ($recordings as $recording) {
            // save a recording file to a temp path
            $recordingPath[$recording->fileID] = "recordings/{$isCallID}/{$recording->fileID}.wav";
            Storage::put($recordingPath[$recording->fileID], $recording->Data);
        }

        if (count($recordingPath) === 0) {
            Storage::deleteDirectory("recordings/{$isCallID}");
            Storage::delete("recordings/{$isCallID}.wav");

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

            return CommandStatus::FAILURE;
        }

        // executes after the command finishes
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
