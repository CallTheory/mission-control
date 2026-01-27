<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\VoicemailDigestMailable;
use App\Models\Stats\Calls\CallLog;
use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class SendVoicemailDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public VoicemailDigest $schedule;

    public Carbon $startDate;

    public Carbon $endDate;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(VoicemailDigest $schedule, Carbon $startDate, Carbon $endDate)
    {
        $this->schedule = $schedule;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->queue = 'voicemail-digest';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Query calls with recordings for the account
            $calls = $this->fetchCallsWithRecordings();

            if (empty($calls)) {
                Log::info("No recordings found for schedule {$this->schedule->id} between {$this->startDate} and {$this->endDate}");

                return;
            }

            // Convert recordings to MP3 and collect data
            $recordings = [];
            foreach ($calls as $call) {
                $recording = $this->processRecording($call);
                if ($recording) {
                    $recordings[] = $recording;
                }
            }

            if (empty($recordings)) {
                Log::info("No recordings could be processed for schedule {$this->schedule->id}");

                return;
            }

            // Send the email
            Mail::send(new VoicemailDigestMailable(
                $this->schedule,
                $recordings,
                $this->startDate,
                $this->endDate
            ));

            Log::info("Voicemail digest sent for schedule {$this->schedule->id} with ".count($recordings).' recordings');

        } catch (Exception $e) {
            Log::error("Failed to send voicemail digest for schedule {$this->schedule->id}: ".$e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch calls with recordings for the configured account.
     */
    private function fetchCallsWithRecordings(): array
    {
        $team = $this->schedule->team;

        // Determine account filter
        $clientNumber = $this->schedule->client_number;
        $billingCode = $this->schedule->billing_code;

        // Build allowed accounts/billing based on schedule config
        $allowedAccounts = $clientNumber ?: $team->allowed_accounts;
        $allowedBilling = $billingCode ?: $team->allowed_billing;

        try {
            $callLog = new CallLog(
                $this->startDate->format('Y-m-d H:i:s'),
                $this->endDate->format('Y-m-d H:i:s'),
                $this->schedule->timezone,
                $clientNumber,  // client_number filter
                null,           // ani
                null,           // call_type
                null,           // agent
                null,           // min_duration
                null,           // max_duration
                null,           // keyword
                null,           // keyword_search
                'statCallStart.Stamp', // sort_by
                'desc',         // sort_direction
                null,           // hasMessages
                true,           // hasRecordings - only calls with recordings
                null,           // hasVideo
                false,          // hasAny
                $allowedAccounts,
                $allowedBilling,
            );

            return $callLog->results ?? [];
        } catch (Exception $e) {
            Log::error('Failed to fetch calls: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Process a single recording - convert to MP3 and fetch transcription.
     */
    private function processRecording(object $call): ?array
    {
        $isCallID = $call->CallId;

        try {
            // Convert to MP3 using artisan command
            Artisan::call('recording:convert-mp3', ['isCallID' => $isCallID]);

            // Get MP3 data from Redis
            $mp3Data = Redis::get("{$isCallID}.mp3");

            if (! $mp3Data) {
                Log::warning("No MP3 data found for call {$isCallID}");

                return null;
            }

            // Get transcription if enabled
            $transcription = null;
            if ($this->schedule->include_transcription) {
                $transcriptionJson = Redis::get("{$isCallID}.json");
                if ($transcriptionJson) {
                    $transcription = json_decode($transcriptionJson, true);
                }
            }

            return [
                'call_id' => $isCallID,
                'client_number' => $call->ClientNumber ?? null,
                'client_name' => $call->ClientName ?? null,
                'call_start' => $call->CallStart ?? null,
                'call_end' => $call->CallEnd ?? null,
                'caller_ani' => $call->CallerANI ?? null,
                'caller_name' => $call->CallerName ?? null,
                'agent_name' => $call->AgentName ?? null,
                'agent_initials' => $call->AgentInitials ?? null,
                'duration' => $call->CallDuration ?? null,
                'mp3_data' => base64_encode($mp3Data),
                'transcription' => $transcription,
            ];
        } catch (Exception $e) {
            Log::error("Failed to process recording for call {$isCallID}: ".$e->getMessage());

            return null;
        }
    }
}
