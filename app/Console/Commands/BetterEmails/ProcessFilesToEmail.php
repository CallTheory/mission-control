<?php

namespace App\Console\Commands\BetterEmails;

use App\Jobs\BeautifyEmail;
use App\Models\BetterEmails;
use App\Models\Stats\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ProcessFilesToEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'better-emails:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Picks up logs written to file and formats them nicely before sending.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if(!Helpers::isSystemFeatureEnabled('better-emails')) {
            // Success, so we don't throw an error in the logs (Stack Trace, etc.)
            return CommandStatus::SUCCESS;
        }

        $betterEmailSetups = BetterEmails::all();

        foreach($betterEmailSetups as $config){

            $fileWriteFolder = storage_path("app/better-emails/{$config->client_number}/{$config->id}/");
            $filesToProcess = array_diff(scandir($fileWriteFolder), ['.', '..', '.gitignore']);

            foreach ($filesToProcess as $file) {

                $parsedLog = Helpers::parseMessageLog("better-emails/{$config->client_number}/{$config->id}/{$file}");
                $envelope = $parsedLog['envelope'] ?? null;
                $log = $parsedLog['log'] ?? null;

                BeautifyEmail::dispatch($config, $log, $envelope);
                //delete the job so it's not processed again
                Storage::delete("better-emails/{$config->client_number}/{$config->id}/{$file}");
            }
        }

        return CommandStatus::SUCCESS;
    }
}
