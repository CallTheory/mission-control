<?php

namespace App\Jobs\PeoplePraiseApi;

use App\Models\DataSource;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use App\Utilities\RenderMessageSummary;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreatePrecisionJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 45;

    public int $api_timeout = 30;

    public int $tries = 3;

    public int $retryAfter = 60;

    public string $api_endpoint = 'https://peoplepraise.net/api/rest/precision/create/';

    public string $initial;

    public string $client_id;

    public string $event_type;

    public string $event_datetime;

    public string $create_datetime;

    public string $notes;

    public string $reporter_initial;

    public string $call_id;

    // The board-check item this export represents. It is deleted only once the export
    // is confirmed successful, so a failed/retrying export never loses the record.
    public ?int $board_check_item_id;

    /**
     * Create a new job instance.
     */
    public function __construct($initial, $client_id, $event_type, $event_datetime, $create_datetime, $notes, $reporter_initial, $call_id, $board_check_item_id = null)
    {
        $this->initial = $initial;
        $this->client_id = $client_id;
        $this->event_type = $event_type;
        $this->event_datetime = $event_datetime;
        $this->create_datetime = $create_datetime;
        $this->notes = $notes;
        $this->reporter_initial = $reporter_initial;
        $this->call_id = $call_id;
        $this->board_check_item_id = $board_check_item_id;
        $this->queue = 'people-praise';
    }

    /**
     * Execute the job.
     *
     * @throws Exception|GuzzleException
     */
    public function handle(): void
    {
        $client = new Client;
        try {
            $datasource = DataSource::firstOrFail();
            $username = $datasource->people_praise_basic_auth_user;
            $password = $datasource->people_praise_basic_auth_pass;
        } catch (Exception $e) {
            // Don't silently succeed on a missing/undecryptable credential — surface
            // it so the job is retried/failed rather than dropping the export.
            Log::error('PeoplePraise API: unable to load credentials for call ID '.$this->call_id.': '.$e->getMessage());
            throw $e;
        }

        // Get timezone from Settings model
        $settings = Settings::first();
        $timezone = $settings->switch_data_timezone ?? 'UTC';

        $callData = new Call(['ISCallId' => $this->call_id]);
        $call = $callData->results[0];
        $messageData = $call->messages[0] ?? null;
        if (is_null($messageData)) {
            $message = 'No Message';
        } else {
            $message = $messageData->Summary ?? 'No Message';
        }

        // Generate screenshot and validate it's not empty
        $screenshotBase64 = RenderMessageSummary::htmlToImage(Helpers::formatMessageSummary($message));

        // If screenshot generation fails, log and retry
        if (empty($screenshotBase64)) {
            Log::error('PeoplePraise API: Screenshot generation failed for call ID '.$this->call_id.', returning empty base64 data');
            throw new Exception('Screenshot generation failed - received empty base64 data');
        }

        // Validate the base64 data is actually an image
        if (! preg_match('/^[a-zA-Z0-9\/\+]+=*$/', $screenshotBase64)) {
            Log::error('PeoplePraise API: Invalid base64 data for call ID '.$this->call_id);
            throw new Exception('Screenshot generation failed - invalid base64 format');
        }

        $response = $client->request('POST', $this->api_endpoint, [
            'auth' => [$username, $password],
            'timeout' => $this->api_timeout,
            'json' => [
                'initial' => $this->initial,
                'client_id' => $this->client_id,
                'event_type' => $this->event_type,
                'event_datetime' => Carbon::parse($this->event_datetime, $timezone)->format('Y-m-d H:i:s'),
                'create_datetime' => Carbon::parse($this->create_datetime, $timezone)->format('Y-m-d H:i:s'),
                'notes' => $this->notes,
                'reporter_initial' => $this->reporter_initial,
                'object_id' => $this->call_id,
                'files' => [
                    [
                        'filename' => "{$this->call_id}.png",
                        'filedata' => $screenshotBase64,
                    ],
                ],
            ],
        ]);

        // A non-200 must propagate so the job is retried; previously the throw was
        // caught by its own try/catch and swallowed, so retries never happened.
        if ($response->getStatusCode() !== 200) {
            throw new Exception('Failed to create precision job (HTTP '.$response->getStatusCode().')');
        }

        // Export confirmed — now it's safe to remove the source board-check item.
        if ($this->board_check_item_id !== null) {
            \App\Models\BoardCheckItem::whereKey($this->board_check_item_id)->delete();
        }
    }

    public function uniqueId(): string
    {
        return $this->call_id;
    }
}
