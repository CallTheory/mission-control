<?php

declare(strict_types=1);

namespace App\Console\Commands\MessageExports;

use App\Jobs\ProcessMessageExport;
use App\Models\MessageExport;
use App\Models\Stats\Helpers;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Command\Command as CommandStatus;
use Throwable;

class ProcessScheduledMessageExports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message-export:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled message export dispatches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Helpers::isSystemFeatureEnabled('message-export')) {
            $this->info('Message export feature is not enabled.');

            return CommandStatus::SUCCESS;
        }

        $exports = MessageExport::where('enabled', true)
            ->where('schedule_type', '!=', 'manual')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', Carbon::now())
            ->get();

        if ($exports->isEmpty()) {
            $this->info('No message export schedules are due.');

            return CommandStatus::SUCCESS;
        }

        foreach ($exports as $export) {
            try {
                if (! $export->team->utility_message_export) {
                    continue;
                }

                $this->info("Processing message export: {$export->name} (ID: {$export->id})");

                [$startDate, $endDate] = $export->getDateRange();

                $export->last_run_at = Carbon::now();
                $export->next_run_at = $export->calculateNextRunAt();
                $export->save();

                ProcessMessageExport::dispatch($export, $startDate, $endDate);

                $this->info("Dispatched job for export {$export->id}. Next run: {$export->next_run_at}");
            } catch (Throwable $e) {
                Log::error("Failed to schedule message export {$export->id}: ".$e->getMessage());
                $this->error("Failed to schedule export {$export->id}: ".$e->getMessage());
            }
        }

        return CommandStatus::SUCCESS;
    }
}
