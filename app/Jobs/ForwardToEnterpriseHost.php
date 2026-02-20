<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EnterpriseHost;
use App\Models\WctpMessage;
use App\Services\WctpService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ForwardToEnterpriseHost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $backoff;

    public int $timeout;

    public function __construct(
        protected EnterpriseHost $host,
        protected WctpMessage $message,
        protected string $wctpXml,
    ) {
        $this->tries = config('wctp.forwarding.retries', 10);
        $this->backoff = config('wctp.forwarding.retry_delay', 60);
        $this->timeout = config('wctp.forwarding.timeout', 30);
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        return now()->addMinutes(30);
    }

    /**
     * Execute the job.
     */
    public function handle(WctpService $wctpService): void
    {
        $verifyCerts = config('wctp.verify_certificates', true);

        try {
            $response = Http::timeout($this->timeout)
                ->withOptions(['verify' => $verifyCerts])
                ->withHeaders(['Content-Type' => 'text/xml; charset=UTF-8'])
                ->withBody($this->wctpXml, 'text/xml')
                ->post($this->host->callback_url);

            // Validate the response contains a wctp-Confirmation
            if ($response->successful()) {
                $body = $response->body();
                if (! empty($body) && ! str_contains($body, 'wctp-Confirmation')) {
                    Log::warning('Enterprise host did not return wctp-Confirmation', [
                        'host' => $this->host->name,
                        'response_body' => substr($body, 0, 500),
                    ]);
                    // Retry - enterprise host may not have processed it correctly
                    $this->release($this->backoff);

                    return;
                }
            } else {
                Log::warning('Enterprise host returned non-success status', [
                    'host' => $this->host->name,
                    'status' => $response->status(),
                ]);
                $this->release($this->backoff);

                return;
            }

            Log::info('Message forwarded to Enterprise Host', [
                'host' => $this->host->name,
                'callback_url' => $this->host->callback_url,
                'message_id' => $this->message->wctp_message_id,
                'status' => $response->status(),
            ]);

        } catch (Exception $e) {
            Log::error('Failed to forward message to Enterprise Host', [
                'host' => $this->host->name,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            $this->release($this->backoff);
        }
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(?Exception $exception): void
    {
        Log::error('Enterprise host forwarding permanently failed', [
            'host' => $this->host->name,
            'message_id' => $this->message->wctp_message_id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
