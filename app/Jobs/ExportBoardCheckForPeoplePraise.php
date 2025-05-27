<?php

namespace App\Jobs;

use App\Models\BoardCheckItem;
use App\Models\Stats\Agents\Agent;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExportBoardCheckForPeoplePraise implements ShouldQueue, ShouldBeUnique, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $markedOk;

    public $markedProblem;

    public $settings;

    public int $largestMsgId = 0;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->settings = Settings::first();
        $this->markedOk = BoardCheckItem::whereNotNull('marked_ok_at')->get();
        $this->markedProblem = BoardCheckItem::whereNotNull('problem_verified_at')->get();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if(Helpers::isSystemFeatureEnabled('board-check')){
            $bcc = Helpers::boardCheckCategories();

            if (is_null($this->settings)) {
                return;
            }

            if ($this->markedOk->count()) {
                foreach ($this->markedOk as $item) {
                    if ($item->msgId > $this->largestMsgId) {
                        $this->largestMsgId = $item->msgId;
                    }

                    try {
                        $item->delete();
                    } catch (Exception $e) {
                        Log::critical('Unable to delete Marked OK item: '.$e->getMessage());
                    }
                }
            }

            if ($this->markedProblem->count()) {
                $fileCreatedTime = Carbon::now($this->settings->switch_data_timezone ?? 'UTC')->format('Y-m-d-His');
                $filePath = "board_check_{$fileCreatedTime}.csv";

                foreach ($this->markedProblem as $item) {

                    if ($item->msgId > $this->largestMsgId) {
                        $this->largestMsgId = $item->msgId;
                    }

                    try {
                        $agentData = new Agent(['agtId' => $item->agtId]);
                        $agent = $agentData->results[0];
                        $callData = new Call(['ISCallId' => $item->callId]);
                        $call = $callData->results[0];
                    } catch (Exception $e) {
                        Log::critical('Unable to get details: '.$e->getMessage());

                        return;
                    }

                    /*
                    * AgentName
                    * AgentInitials
                    * EventDate
                    * EventTime
                    * EventCategory
                    * EventComments
                    * ClientName
                    * ClientNumber
                    * BillingCode
                    * CallId
                    */

                    $row = [
                        $agent->Name ?? 'UNKNOWN',
                        $agent->Initials ?? 'AAA',
                        Carbon::parse($call->CallStart ?? 'now', $this->settings->switch_data_timezone ?? 'UTC')->format('Y-m-d'),
                        Carbon::parse($call->CallStart ?? 'now', $this->settings->switch_data_timezone ?? 'UTC')->format('H:i:s'),
                        $bcc[$item->category] ?? 'None',
                        $item->comments ?? '',
                        $call->ClientName ?? 'UNKNOWN',
                        $call->ClientNumber ?? '0',
                        $call->BillingCode ?? '0',
                        $item->callId ?? '0',
                    ];

                    try {
                        $handle = fopen(storage_path("app/people-praise/{$filePath}"), 'a');
                        fputcsv($handle, $row);
                        fclose($handle);
                        $item->delete();
                    } catch (Exception $e) {
                        Log::critical('Unable to save row to file: '.$e->getMessage());
                    }
                }
            }

            //update if `largestMsgId` isn't 0, and it's bigger than the existing setting.
            if ($this->largestMsgId > 0 && $this->largestMsgId > $this->settings->board_check_starting_msgId) {
                $this->settings->board_check_starting_msgId = $this->largestMsgId;
                try {
                    $this->settings->save();
                } catch (Exception $e) {
                    Log::critical('Unable to save system setting for board check starting msgId: '.$e->getMessage());
                }
            }
        }
    }
}
