<?php

namespace App\Console\Commands\ISFaxing;

use App\Mail\FaxBuildupAlert;
use App\Models\Stats\Helpers;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandStatus;

class MonitorFaxBuildup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'isfax:monitor {fax_provider}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the fax folders to determine if faxes are not being processed.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return CommandStatus::FAILURE;
        }

        $fax_provider = $this->argument('fax_provider');
        $toSendPath = storage_path("app/{$fax_provider}/tosend/");
        $failPath = storage_path("app/{$fax_provider}/fail/");
        $sentPath = storage_path("app/{$fax_provider}/sent/");

        $paths = null;

        if ($this->hasSittingFiles($toSendPath)) {
            $paths[] = $toSendPath;
        }

        if ($this->hasSittingFiles($failPath)) {
            $paths[] = $failPath;
        }

        if ($this->hasSittingFiles($sentPath)) {
            $paths[] = $sentPath;
        }

        if ($paths !== null) {
            $this->info('Found unexpected fax files older than 15 minutes');
            Mail::queue(new FaxBuildupAlert($paths));
        }

        return CommandStatus::SUCCESS;
    }

    private function hasSittingFiles(string $path): bool
    {
        $faxes = scandir($path);

        foreach ($faxes as $fax) {
            if (Str::endsWith($fax, ['.cap', '.fs'])) {
                $fileTime = filectime("{$path}{$fax}");
                $oldestAllowed = Carbon::now()->subMinutes(15)->timestamp;

                if ($oldestAllowed > $fileTime) {
                    $this->info("[{$path}] [{$fax}]");
                    $this->info('File Time: '.Carbon::createFromTimestampUTC($fileTime));
                    $this->info('Oldest Allowed:'.Carbon::createFromTimestampUTC($oldestAllowed));
                    $this->comment($oldestAllowed - $fileTime);

                    return true;
                }
            }
        }

        return false;
    }
}
