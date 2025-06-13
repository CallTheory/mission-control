<?php

namespace App\Jobs;

use App\Mail\SendToAmtelcoSmtp;
use App\Models\DataSource;
use App\Models\InboundEmail;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendEmailToAmtelcoSMTP implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public InboundEmail $email;

    public string $category;

    public DataSource $datasource;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     */
    public function __construct(InboundEmail $email, string $category)
    {
        $this->datasource = DataSource::firstOrFail();
        $this->email = $email;
        $this->category = $category;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $params = json_encode([
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
        ]);

        Config::set('mail.mailers.is-smtp', [
            'transport' => 'smtp',
            'host' => $this->datasource->amtelco_inbound_smtp_host,
            'port' => $this->datasource->amtelco_inbound_smtp_port ?? 25,
            // No amtelco support for basic security
            'encryption' => false,
        ]);

        try {
            Mail::mailer('is-smtp')->to($this->email->to)->send(new SendToAmtelcoSmtp($this->email->subject, $params));
        } catch (Exception $e) {
            Log::error($e->getMessage());
        }

        $this->email->processed_at = Carbon::now();
        $this->email->save();
    }
}
