<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\InboundEmail;
use App\Models\MergeCommISWebTrigger;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;

class SendEmailToMergeComm implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public InboundEmail $email;

    public string $category;

    public MergeCommISWebTrigger $config;

    public DataSource $datasource;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(InboundEmail $email, string $category, MergeCommISWebTrigger $config)
    {
        $this->email = $email;
        $this->category = $category;
        $this->config = $config;
    }

    /**
     * Execute the job.
     *
     * @throws GuzzleException
     * @throws Exception
     */
    public function handle(): void
    {
        $this->datasource = DataSource::firstOrFail();

        $verify = true;

        if (App::environment('local')) {
            $verify = false;
        }

        $client = new Client([
            'timeout' => 30.0,
            'verify' => $verify,
        ]);

        $params = [
            'clientId' => $this->config->clientId,
            // 'clientNumber' => '6145551234', //undocumented, but working, per amtelco...?????
            'listId' => 0,
            'apiKey' => $this->config->apiKey,
            'Parameters' => 'email',
            'ParameterValues' => json_encode([
                'id' => urlencode($this->email->id),
                'to' => urlencode($this->email->to),
                'from' => urlencode($this->email->from),
                'subject' => urlencode($this->email->subject),
                'text' => urlencode($this->email->text),
                'category' => urlencode($this->category ?? 'none'),
                'actions' => [
                    'forward_link' => urlencode(secure_url("/api/agents/inbound-email/forward/{$this->email->id}")),
                    'view_link' => urlencode(URL::temporarySignedRoute(
                        'api.agents.inbound-email.view', now()->addHours(24), ['email' => $this->email->id]
                    )),
                ],
            ]),
            'login' => $this->config->login,
            'password' => $this->config->password,
            'message' => $this->config->message,
        ];

        try {
            $response = $client->request('POST', "{$this->datasource->is_web_api_endpoint}/Message/MergeComm", [
                'form_params' => $params,
            ]);

            $body = $response->getBody()->getContents();
            $status = $response->getStatusCode();
        } catch (Exception $e) {
            throw new Exception("ISWeb API connection error: {$e->getMessage()}");
        }

        if ($status != 200) {
            throw new Exception("ISWeb API responded with: {$status}({$body})");
        }

        $this->email->processed_at = Carbon::now();
        $this->email->save();
    }
}
