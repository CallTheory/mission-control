<?php

declare(strict_types=1);

namespace App\Console\Commands\ISFaxing;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandStatus;

/**
 * @deprecated Superseded by isfax:scan. See ProcessNewFaxes.
 */
class ProcessRingCentralNewFaxes extends Command
{
    protected $signature = 'isfax:process-ring-central';

    protected $description = '[Deprecated] Use isfax:scan. Processes new RingCentral faxes in the TOSEND folder.';

    public function handle(): int
    {
        $this->warn('isfax:process-ring-central is deprecated; use "isfax:scan --provider=ringcentral" instead.');

        return $this->call('isfax:scan', ['--provider' => ['ringcentral']]) === 0
            ? CommandStatus::SUCCESS
            : CommandStatus::FAILURE;
    }
}
