<?php

namespace App\Console\Commands\ISFaxing;

use App\Jobs\SendFaxRingCentral;
use App\Models\DataSource;
use App\Models\Stats\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandStatus;

class ProcessRingCentralNewFaxes extends Command
{
    private DataSource $datasource;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'isfax:process-ring-central';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process new faxes in the TOSEND folder for Ring Central';

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

        if ($this->datasource->ringcentral_client_id &&
            decrypt($this->datasource->ringcentral_client_secret) &&
            decrypt($this->datasource->ringcentral_jwt_token) &&
            $this->datasource->ringcentral_api_endpoint
        ) {
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

        // check to see if Ring Central is even set up...
        if (! $this->isFaxEnabled()) {
            return CommandStatus::FAILURE;
        }

        $toSendPath = storage_path('app/ringcentral/tosend/');
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

                if(config('app.switch_engine') === 'infinity'){

                    /**
                     * $var_def DATA5 "4234"
                     * $var_def ASCII_TEMPLATE C:\Copia\FaxFacts\STANDARD.GTT
                     * $fax_filename C:\Copia\FaxFacts\callback\messages\00006101.cap
                     * $fax_phone "95551234567"
                     * $fax_status1 2
                     * $fax_origin user_request
                     * $fax_user C:\Copia\FaxFacts\fax.usr
                     * $fax_receiver "Patrick's Heating & A/C     95551234567"
                     * $fax_header "Patrick's Heating & A/C  Message's from your service"
                     * $fax_request_date 07/03/25
                     * $fax_request_time 13:11:28
                     */
                    foreach ($lines as $line) {

                        if (Str::startsWith($line, '$var_def DATA5')) {
                            $exploded = array_values(array_filter(explode('$var_def DATA5 ', $line)));
                            $isfax['jobID'] = str_replace('"', '', $exploded[0] ?? '') ?? null;
                        } elseif (Str::startsWith($line, '$fax_filename')) {
                            $exploded = array_values(array_filter(explode('$fax_filename ', $line)));
                            $filename = explode('\\', $exploded[0]);
                            if (Str::contains(end($filename), '"')) {
                                $filetemp = explode('"', end($filename));
                                $isfax['capfile'] = reset($filetemp);
                            } else {
                                $isfax['capfile'] = end($filename);
                            }
                            $isfax['filename'] = $isfax['capfile'];
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
                }
                else{
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
                    $this->error("Missing expected fax details...\n\n" . implode('. ', $validator->errors()->all()) . "\n\n".print_r($isfax, true));
                } else {
                    $this->info("Submitting fax job {$isfax['jobID']}");
                    $this->info(print_r($isfax, true));
                    SendFaxRingCentral::dispatch($isfax);
                }
            } else {
                $this->comment("[IGNORE] {$fax}");
            }
        }

        return CommandStatus::SUCCESS;
    }
}
