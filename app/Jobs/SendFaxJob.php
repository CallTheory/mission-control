<?php

namespace App\Jobs;

use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\Stats\Helpers;
use Exception;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendFaxJob implements ShouldQueue, ShouldBeUnique, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $jobID;

    public string $fsFileName;

    public string $capfile;

    public string $filename;

    public string $phone;

    public string $status;

    public string $mFaxApiKey;

    public string $notes;

    public string $subject;

    public string $senderName;

    public string $coverPageId;

    public DataSource $datasource;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $fax)
    {
        $this->datasource = DataSource::firstOrFail();
        $this->jobID = $fax['jobID'];
        $this->capfile = $fax['capfile'];
        $this->filename = $fax['filename'];
        $this->phone = str_ireplace(['-', '.', ' ', '(', ')'], '', $fax['phone']);
        $this->status = $fax['status'];
        $this->fsFileName = $fax['fsFileName'];

        $this->notes = $this->datasource->mfax_notes ?? '';

        $this->mFaxApiKey = decrypt($this->datasource->mfax_api_key);
        $this->coverPageId = $this->datasource->mfax_cover_page_id;
        $this->subject = $this->datasource->mfax_subject;
        $this->senderName = $this->datasource->mfax_sender_name;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws GuzzleException
     */
    public function handle()
    {
        if(Helpers::isSystemFeatureEnabled('cloud-faxing')){

            if (! Str::startsWith($this->phone, '+') && strlen($this->phone) === 10) {
                $toNumber = "+1{$this->phone}";
            } elseif (! Str::startsWith($this->phone, '+')) {
                $toNumber = "+{$this->phone}";
            } else {
                $toNumber = "{$this->phone}";
            }

            try {
                $isClientInfo = $this->getClientInfo();

                if ($isClientInfo === null) {
                    $isClientInfo['ClientName'] = $toNumber;
                    $isClientInfo['ClientNumber'] = 'Unknown';
                }
            } catch (Exception $e) {
                $isClientInfo['ClientName'] = $toNumber;
                $isClientInfo['ClientNumber'] = 'Unknown';
            }

            $guzzle = new Guzzle([
                'base_uri' => 'https://api.documo.com/',
                'timeout' => 30.0,
                'headers' => [
                    'Authorization' => "Basic {$this->mFaxApiKey}",
                ],
            ]);

            try {
                $tags = $guzzle->get('/v1/tags');
            } catch (Exception $e) {
            }

            $existingTagList = [];

            if (isset($tags) && $tags->getStatusCode() === 200) {
                $existingTagList = json_decode((string) $tags->getBody(), true);
            }

            $useTag = null;

            foreach ($existingTagList['rows'] ?? []  as $existingTag) {
                if ($existingTag['name'] === (string) $isClientInfo['ClientNumber']) {
                    $useTag = $existingTag;
                }
            }

            if (is_null($useTag)) {
                try {
                    $createTag = $guzzle->post('/v1/tags', [
                        'form_params' => [
                            'name' => $isClientInfo['ClientNumber'],
                            'color' => '#22d3ee',
                            'isPublic' => true,
                        ],
                    ]);
                } catch (Exception $e) {
                    $useTag['uuid'] = '';
                }
            }

            if (isset($createTag) && $createTag->getStatusCode() === 200) {
                $newlyCreated = json_decode((string) $createTag->getBody(), true);
                $useTag['uuid'] = $newlyCreated['uuid'] ?? '';
            }

            $faxFsDetails = [
                'jobID' => $this->jobID,
                'capfile' => $this->capfile,
                'filename' => $this->filename,
                'phone' => $this->phone,
                'status' => $this->status,
                'fsFileName' => $this->fsFileName,
            ];

            $useCoverPage = 'false';
            $coverPageDetails = [];

            $attachments = [
                'name' => 'attachments',
                'contents' => file_get_contents(storage_path('app/mfax/tosend/'.$this->capfile)),
                'filename' => str_replace('.cap', '.txt', $this->capfile),
                'headers' => [
                    'content-type' => 'text/plain',
                ],
            ];

            if (strlen($this->coverPageId) > 0) {
                $useCoverPage = 'true';
                $attachments['name'] = str_replace('.cap', '', $this->capfile);

                $coverPageDetails = ['name' => 'coverPageId', 'contents' => $this->coverPageId];
                $request = [
                    'multipart' => [
                        ['name' => 'notes', 'contents' => Str::substr($this->notes, 0, 4000)],
                        ['name' => 'faxNumber', 'contents' => $toNumber],
                        ['name' => 'subject', 'contents' => Str::substr($this->subject, 0, 55)],
                        ['name' => 'coverPage', 'contents' => $useCoverPage],
                        $coverPageDetails,
                        ['name' => 'recipientName', 'contents' => Str::substr($isClientInfo['ClientName'], 0, 40)],
                        ['name' => 'senderName', 'contents' => Str::substr($this->senderName, 0, 40)],
                        ['name' => 'tags', 'contents' => $useTag['uuid'] ?? ''],
                        $attachments,
                    ],
                ];
            }
            else
            {
                $request = [
                    'multipart' => [
                        ['name' => 'notes', 'contents' => Str::substr($this->notes, 0, 4000)],
                        ['name' => 'faxNumber', 'contents' => $toNumber],
                        ['name' => 'subject', 'contents' => Str::substr($this->subject, 0, 55)],
                        ['name' => 'coverPage', 'contents' => $useCoverPage],
                        ['name' => 'recipientName', 'contents' => Str::substr($isClientInfo['ClientName'], 0, 40)],
                        ['name' => 'senderName', 'contents' => Str::substr($this->senderName, 0, 40)],
                        ['name' => 'tags', 'contents' => $useTag['uuid'] ?? ''],
                        $attachments,
                    ],
                ];
            }

            try {
                $response = $guzzle->post('/v1/faxes', $request);
            } catch (Exception $e) {
                Log::error($e->getMessage(), $request);
                Mail::queue(new FaxFailAlert($faxFsDetails, $e->getMessage()));
                MoveFailedFaxFiles::dispatch($faxFsDetails);

                return;
            }

            if ($response->getStatusCode() === 200) {
                MoveSuccessfulFaxFiles::dispatch($faxFsDetails);

                return;
            }

            Log::error('mFax HTTP Response: '.$response->getStatusCode());
            Mail::queue(new FaxFailAlert($faxFsDetails, 'mFax HTTP Response: '.$response->getStatusCode()));
            MoveFailedFaxFiles::dispatch($faxFsDetails);
        }
    }

    public function uniqueId()
    {
        return $this->jobID;
    }

    /**
     * @throws Exception
     */
    private function getClientInfo(): array|null
    {
        $results = null;

        Config::set('database.connections.intelligent', [
            'driver' => 'sqlsrv',
            'host' => $this->datasource->is_db_host,
            'port' => $this->datasource->is_db_port,
            'database' => $this->datasource->is_db_data,
            'username' => $this->datasource->is_db_user,
            'password' => decrypt($this->datasource->is_db_pass),
            'encrypt' => true,
            'trust_server_certificate' => true,
        ]);

        try {
            $sql = 'select c.ClientNumber as ClientNumber, c.ClientName as ClientName from faxJobs f left join cltClients c on f.cltID = c.cltId where f.jobid = ?';
            $params = [
                $this->jobID,
            ];
            $results = DB::connection('intelligent')->select($sql, array_values($params));
        } catch (Exception $e) {
            if (App::environment('local')) {
                throw $e;
            }
            throw new Exception('Unable to query call data.');
        }

        if (isset($results[0])) {
            return [
                'ClientName' => $results[0]->ClientName ?? null,
                'ClientNumber' => $results[0]->ClientNumber ?? null,
            ];
        }

        return null;
    }
}
