<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ProcessingCleanup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:processing-cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cleans up transcriptions, screen-captures, and recording artifacts.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $recordings = Storage::allFiles('recordings');
        $transcriptions = Storage::allFiles('transcriptions');
        $screencaptures = Storage::allFiles('screencapture');

        $this->info('Checking recording wav files...');
        foreach($recordings as $file)
        {
            $this->cleanupFile($file, '.wav');
        }

        $this->info('Checking transcription json files...');
        foreach($transcriptions as $file){
            $this->cleanupFile($file, '.json');
        }

        $this->info('Checking screen capture mp4 files...');
        foreach($screencaptures as $file) {
            $this->cleanupFile($file, '.mp4');
        }

        $this->info('Complete!');

        return CommandStatus::SUCCESS;
    }

    private function cleanupFile($file, $extension): void
    {
        if(Str::endsWith($file, $extension)){
            $modified = Carbon::createFromTimestampUTC(Storage::lastModified($file));
            if($modified->lt(Carbon::now('UTC')->subHours()))
            {
                $this->info("Removing {$file}");
                Storage::delete($file);
            }
        }
        else{
            $this->info("Skipping {$file}");
        }
    }
}
