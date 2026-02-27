<?php

namespace App\Console\Commands\ISFaxing;

use App\Jobs\SendFaxJob;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ProcessNewFaxes extends Command
{
    private DataSource $datasource;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'isfax:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process new faxes in the TOSEND folder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function isFaxEnabled(): bool
    {
        if (! Helpers::isSystemFeatureEnabled('cloud-faxing')) {
            return false;
        }

        // Only an API key is required
        if ($this->datasource->mfax_api_key !== null) {
            return true;
        }

        return false;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->datasource = DataSource::firstOrFail();

        // check to see if mFax is even setup...
        if (! $this->isFaxEnabled()) {
            return CommandStatus::SUCCESS;
        }

        $toSendPath = storage_path('app/mfax/tosend/');
        $faxesToSend = scandir($toSendPath);

        foreach ($faxesToSend as $fax) {
            if (Str::endsWith($fax, '.fs')) {

                $isfax['fsFileName'] = $fax;
                $this->info("[PROCESS] {$fax}");
                $fsFile = "{$toSendPath}{$fax}";
                $lines = file_get_contents($fsFile);
                $lines = array_filter(explode("\r\n", $lines));

                if (count($lines) <= 1) {
                    $lines = file_get_contents($fsFile);
                    $lines = array_filter(explode("\n", $lines));
                }

                $this->warn(print_r($lines, true));

                foreach ($lines as $line) {

                    if (Str::startsWith($line, '$var_def DATA5')) {

                        $exploded = array_values(array_filter(explode('$var_def DATA5 ', $line)));
                        $isfax['jobID'] = str_replace('"', '', $exploded[0] ?? '') ?? null;

                    } elseif (Str::startsWith($line, '$var_def DATA6')) {

                        $exploded = array_values(array_filter(explode('$var_def DATA6 ', $line)));
                        $capfile = explode('\\', $exploded[0]);
                        $isfax['capfile'] = str_replace('"', '', end($capfile));

                    } elseif (Str::startsWith($line, '$fax_filename')) {

                        $exploded = array_values(array_filter(explode('$fax_filename ', $line)));
                        $filename = explode('\\', $exploded[0]);

                        if (Str::contains(end($filename), '"')) {

                            $filetemp = explode('"', end($filename));
                            $isfax['filename'] = reset($filetemp);

                        } else {

                            $isfax['filename'] = end($filename);

                        }
                    } elseif (Str::startsWith($line, '$fax_phone')) {

                        $exploded = array_values(array_filter(explode('$fax_phone ', $line)));
                        $isfax['phone'] = $exploded[0] ?? null;
                        $isfax['phone'] = str_replace('"', '', $isfax['phone'] ?? '');

                    } elseif (Str::startsWith($line, '$fax_status1')) {

                        $exploded = array_values(array_filter(explode('$fax_status1 ', $line)));

                        if (Str::contains($exploded[0] ?? '', ' ')) {

                            $statustemp = explode(' ', $exploded[0]);
                            $isfax['status'] = reset($statustemp);

                        } else {
                            $isfax['status'] = $exploded[0] ?? null;
                        }
                    }
                }

                $validator = Validator::make($isfax, [
                    'jobID' => 'required|integer',
                    'capfile' => 'required|string|ends_with:.cap',
                    'fsFileName' => 'required|string|ends_with:.fs',
                    'filename' => 'required|string|ends_with:.cap',
                    'phone' => 'required|string',
                    'status' => 'required|string',
                ]);

                if ($validator->fails()) {

                    $this->error("Missing expected fax details...\n".print_r($isfax, true));

                } else {

                    if (PendingFax::where('job_id', $isfax['jobID'])->where('fax_provider', 'mfax')->where('delivery_status', 'pending')->exists()) {
                        $this->comment("Skipping job {$isfax['jobID']} — already pending delivery confirmation.");

                        continue;
                    }

                    $this->info("Submitting fax job {$isfax['jobID']}");
                    $this->info(print_r($isfax, true));
                    SendFaxJob::dispatch($isfax);
                }
            }
        }

        return CommandStatus::SUCCESS;
    }
}
