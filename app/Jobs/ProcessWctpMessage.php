<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WctpMessage;
use App\Services\TwilioService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWctpMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 60, 120, 300, 600];

    protected WctpMessage $message;

    /**
     * Create a new job instance.
     */
    public function __construct(WctpMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): Carbon
    {
        $minutes = config('wctp.processing.retry_until_minutes', 30);

        return now()->addMinutes($minutes);
    }

    /**
     * Execute the job to send SMS via Twilio
     */
    public function handle(TwilioService $twilioService): void
    {
        try {
            // Only process outbound messages
            if ($this->message->direction !== 'outbound') {
                return;
            }

            // Send SMS via Twilio with status callback
            $result = $twilioService->sendSms(
                $this->message->to,
                $this->message->message,
                [
                    'from' => $this->message->from,
                    'statusCallback' => route('wctp.callback', ['messageId' => $this->message->wctp_message_id]),
                ]
            );

            if ($result['success']) {
                $this->message->markAsSent($result['message_sid']);
                $this->message->update(['processed_at' => now()]);

                Log::info('WCTP message sent via Twilio', [
                    'wctp_message_id' => $this->message->wctp_message_id,
                    'twilio_sid' => $result['message_sid'],
                    'to' => $this->message->to,
                    'from' => $this->message->from,
                ]);
            } else {
                throw new Exception($result['error'] ?? 'Unknown error sending message');
            }
        } catch (Exception $e) {
            Log::error('Failed to send WCTP message', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Only mark as permanently failed when all retries are exhausted
            if ($this->attempts() >= $this->tries) {
                $this->message->markAsFailed($e->getMessage());
                $this->fail($e);
            } else {
                // Re-throw to trigger retry with backoff
                throw $e;
            }
        }
    }

    /**
     * Handle a job failure after all retries exhausted.
     */
    public function failed(?Exception $exception): void
    {
        $this->message->markAsFailed($exception?->getMessage());

        Log::error('WCTP message job permanently failed', [
            'message_id' => $this->message->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
