<?php

namespace App\Jobs;

use App\Models\Stats\Calls\Call;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ProcessScreenCapture implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $timeout = 300;

    protected int $processTimeout = 295;

    protected string $isCallID;

    /**
     * Create a new job instance.
     */
    public function __construct(string $isCallID)
    {
        $this->isCallID = $isCallID;
        $this->queue = 'ffmpeg';
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle(): void
    {
        try {
            $call = new Call(['ISCallId' => $this->isCallID, 'ScreenCaptureData' => true]);
        } catch (Exception $e) {
            return;
        }

        // clear any previous artifacts
        $this->cleanupStorage($this->isCallID);

        $screenCapture = $call->screenCapture();
        foreach ($screenCapture as $capture) {
            // save a recording file to a temp path
            $recordingPath[$capture->fileID] = "screencapture/{$this->isCallID}/{$capture->fileID}.mp4";
            Storage::put($recordingPath[$capture->fileID], $capture->Data);
        }

        $files = Storage::files("screencapture/{$this->isCallID}");

        $concat_list = '';

        foreach ($files as $file) {
            $path = storage_path("app/{$file}");
            $concat_list .= "file '{$path}'\n";
        }

        Storage::put("screencapture/{$this->isCallID}.txt", $concat_list);
        $concat_file = storage_path("app/screencapture/{$this->isCallID}.txt");
        $mp4_file = storage_path("app/screencapture/{$this->isCallID}.mp4");

        try {
            // -safe 0 is required to allow for paths ("/") in the file names
            $process = Process::timeout($this->processTimeout)->run("ffmpeg -y -f concat -safe 0 -i {$concat_file} -c:a copy {$mp4_file}")->throw();
        } catch (Exception $e) {
            if (App::environment('local')) {
                throw $e;
            }

            return;
        }

        Redis::setEx("{$this->isCallID}.mp4", 86400, Storage::get("screencapture/{$this->isCallID}.mp4"));
        $this->cleanupStorage($this->isCallID);
    }

    public function uniqueId(): string
    {
        return $this->isCallID;
    }

    protected function cleanupStorage($isCallID): void
    {
        Storage::deleteDirectory("screencapture/{$isCallID}");
        Storage::delete("screencapture/{$isCallID}.txt");
        Storage::delete("screencapture/{$isCallID}.mp4");
    }
}
