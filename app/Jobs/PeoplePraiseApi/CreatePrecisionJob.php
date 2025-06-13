<?php

namespace App\Jobs\PeoplePraiseApi;

use App\Models\DataSource;
use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
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

    /**
     * Create a new job instance.
     */
    public function __construct($initial, $client_id, $event_type, $event_datetime, $create_datetime, $notes, $reporter_initial, $call_id)
    {
        $this->initial = $initial;
        $this->client_id = $client_id;
        $this->event_type = $event_type;
        $this->event_datetime = $event_datetime;
        $this->create_datetime = $create_datetime;
        $this->notes = $notes;
        $this->reporter_initial = $reporter_initial;
        $this->call_id = $call_id;
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
            $username = decrypt($datasource->people_praise_basic_auth_user);
            $password = decrypt($datasource->people_praise_basic_auth_pass);
        } catch (Exception $e) {
            return;
        }

        $callData = new Call(['ISCallId' => $this->call_id]);
        $call = $callData->results[0];
        $messageData = $call->messages[0] ?? null;
        if (is_null($messageData)) {
            $message = 'No Message';
        } else {
            $message = $messageData->Summary ?? 'No Message';
        }

        $response = $client->request('POST', $this->api_endpoint, [
            'auth' => [$username, $password],
            'timeout' => $this->api_timeout,
            'json' => [
                'initial' => $this->initial,
                'client_id' => $this->client_id,
                'event_type' => $this->event_type,
                'event_datetime' => Carbon::parse($this->event_datetime, $datasource->timezone)->format('Y-m-d H:i:s'),
                'create_datetime' => Carbon::parse($this->create_datetime, $datasource->timezone)->format('Y-m-d H:i:s'),
                'notes' => $this->notes,
                'reporter_initial' => $this->reporter_initial,
                'object_id' => $this->call_id,
                'files' => [
                    [
                        'filename' => "{$this->call_id}.png",
                        'filedata' => RenderMessageSummary::htmlToImage(Helpers::formatMessageSummary($message)),
                    ],
                ],
            ],
        ]);

        try {
            if ($response->getStatusCode() !== 200) {
                // forces retry
                throw new Exception('Failed to create precision job');
            } else {
                return;
            }
        } catch (Exception $e) {
            return;
        }
    }

    public function uniqueId(): string
    {
        return $this->call_id;
    }
}
