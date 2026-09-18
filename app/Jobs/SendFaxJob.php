<?php

namespace App\Jobs;

use App\Mail\FaxFailAlert;
use App\Models\DataSource;
use App\Models\PendingFax;
use App\Models\Stats\Helpers;
use App\Services\Faxing\FaxAccountLookup;
use App\Services\Observability\GuzzleTracing;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class SendFaxJob implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60];

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
        // basename() neutralizes any path traversal in filenames sourced from .fs
        // file contents before they are concatenated into storage paths.
        $this->capfile = basename($fax['capfile']);
        $this->filename = basename($fax['filename']);
        // Strip everything that isn't a digit or leading +, e.g. a stray trailing ';'.
        $this->phone = preg_replace('/[^0-9+]/', '', $fax['phone']);
        $this->status = $fax['status'];
        $this->fsFileName = basename($fax['fsFileName']);

        $this->notes = $this->datasource->mfax_notes ?? '';

        $this->mFaxApiKey = $this->datasource->mfax_api_key;
        $this->coverPageId = $this->datasource->mfax_cover_page_id;
        $this->subject = $this->datasource->mfax_subject;
        $this->senderName = $this->datasource->mfax_sender_name;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws GuzzleException
     */
    public function handle()
    {
        if (Helpers::isSystemFeatureEnabled('cloud-faxing')) {

            if (! Str::startsWith($this->phone, '+') && strlen($this->phone) === 10) {
                $toNumber = "+1{$this->phone}";
            } elseif (! Str::startsWith($this->phone, '+')) {
                $toNumber = "+{$this->phone}";
            } else {
                $toNumber = "{$this->phone}";
            }

            // Which Intelligent Series account this fax belongs to. Documo carries it as
            // a tag; it is also persisted on the pending_faxes row so the dashboards, the
            // spool file listings and the alert emails can identify the fax the same way
            // regardless of provider.
            try {
                $account = FaxAccountLookup::make($this->datasource)->forJobId($this->jobID);
            } catch (Exception $e) {
                $account = null;
            }

            $clientName = $account['name'] ?? $toNumber;
            $clientNumber = $account['number'] ?? 'Unknown';

            $guzzle = new Guzzle([
                // null when tracing is off, so Guzzle uses its default handler.
                'handler' => GuzzleTracing::handlerStack(),
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

            foreach ($existingTagList['rows'] ?? [] as $existingTag) {
                if ($existingTag['name'] === $clientNumber) {
                    $useTag = $existingTag;
                }
            }

            if (is_null($useTag)) {
                try {
                    $createTag = $guzzle->post('/v1/tags', [
                        'form_params' => [
                            'name' => $clientNumber,
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
                        ['name' => 'recipientName', 'contents' => Str::substr($clientName, 0, 40)],
                        ['name' => 'senderName', 'contents' => Str::substr($this->senderName, 0, 40)],
                        ['name' => 'tags', 'contents' => $useTag['uuid'] ?? ''],
                        $attachments,
                    ],
                ];
            } else {
                $request = [
                    'multipart' => [
                        ['name' => 'notes', 'contents' => Str::substr($this->notes, 0, 4000)],
                        ['name' => 'faxNumber', 'contents' => $toNumber],
                        ['name' => 'subject', 'contents' => Str::substr($this->subject, 0, 55)],
                        ['name' => 'coverPage', 'contents' => $useCoverPage],
                        ['name' => 'recipientName', 'contents' => Str::substr($clientName, 0, 40)],
                        ['name' => 'senderName', 'contents' => Str::substr($this->senderName, 0, 40)],
                        ['name' => 'tags', 'contents' => $useTag['uuid'] ?? ''],
                        $attachments,
                    ],
                ];
            }

            $response = $guzzle->post('/v1/faxes', $request);

            if ($response->getStatusCode() === 200) {
                $responseBody = json_decode((string) $response->getBody(), true);
                $apiFaxId = $responseBody['uuid'] ?? $responseBody['id'] ?? '';

                PendingFax::create([
                    'api_fax_id' => $apiFaxId,
                    'fax_provider' => 'mfax',
                    'job_id' => $this->jobID,
                    'fs_file_name' => $this->fsFileName,
                    'cap_file' => $this->capfile,
                    'filename' => $this->filename,
                    'phone' => $this->phone,
                    'client_number' => $account['number'] ?? null,
                    'client_name' => $account['name'] ?? null,
                    'original_status' => $this->status,
                    'delivery_status' => 'pending',
                    'submitted_at' => now(),
                ]);

                return;
            }

            throw new Exception('mFax HTTP Response: '.$response->getStatusCode());
        }
    }

    public function failed(Throwable $exception): void
    {
        $faxFsDetails = [
            'jobID' => $this->jobID,
            'capfile' => $this->capfile,
            'filename' => $this->filename,
            'phone' => $this->phone,
            'status' => $this->status,
            'fsFileName' => $this->fsFileName,
            'account' => $this->accountLabel(),
        ];

        Log::error("SendFaxJob failed after {$this->tries} attempts: {$exception->getMessage()}", $faxFsDetails);
        Mail::queue(new FaxFailAlert($faxFsDetails, $exception->getMessage()));
        MoveFailedFaxFiles::dispatch($faxFsDetails);
    }

    /**
     * Which account this fax belonged to, for the failure alert. The lookup is cached
     * from the send attempt, so this costs nothing in the common case.
     */
    private function accountLabel(): string
    {
        try {
            $account = FaxAccountLookup::make($this->datasource)->forJobId($this->jobID);
        } catch (Exception $e) {
            return 'Unknown';
        }

        if ($account === null) {
            return 'Unknown';
        }

        return trim($account['number'].(blank($account['name']) ? '' : " — {$account['name']}"));
    }

    /**
     * Key the unique lock on the per-recipient .fs filename, not the shared jobID
     * (from $var_def DATA5), so a fanned-out .cap reaches every recipient.
     */
    public function uniqueId(): string
    {
        return $this->fsFileName;
    }

    /**
     * Bound the unique lock so an interrupted worker cannot hold it forever. Without a
     * window, a job killed mid-send (deploy, restart, OOM) left a lock nothing would
     * release, and isfax:process silently declined to re-dispatch that .fs again — the
     * file simply sat in tosend/ until somebody noticed.
     */
    public function uniqueFor(): int
    {
        return 3600;
    }
}
