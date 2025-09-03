<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WctpMessage;
use App\Services\TwilioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessWctpMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 180, 600]; // Retry after 1min, 3min, 10min

    protected WctpMessage $message;

    /**
     * Create a new job instance.
     */
    public function __construct(WctpMessage $message)
    {
        $this->message = $message;
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
                    'statusCallback' => route('wctp.callback', ['messageId' => $this->message->wctp_message_id])
                ]
            );

            if ($result['success']) {
                $this->message->markAsSent($result['message_sid']);
                
                Log::info('WCTP message sent via Twilio', [
                    'wctp_message_id' => $this->message->wctp_message_id,
                    'twilio_sid' => $result['message_sid'],
                    'to' => $this->message->to,
                    'from' => $this->message->from
                ]);
            } else {
                throw new Exception($result['error'] ?? 'Unknown error sending message');
            }
        } catch (Exception $e) {
            Log::error('Failed to send WCTP message', [
                'message_id' => $this->message->id,
                'error' => $e->getMessage()
            ]);

            // Mark as failed and let the job fail
            $this->message->markAsFailed($e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        $this->message->markAsFailed($exception->getMessage());
        
        Log::error('WCTP message job failed', [
            'message_id' => $this->message->id,
            'error' => $exception->getMessage()
        ]);
    }
}