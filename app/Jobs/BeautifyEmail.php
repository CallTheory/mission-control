<?php

namespace App\Jobs;

use App\Mail\PrettyLog;
use App\Models\BetterEmails;
use App\Models\Stats\Helpers;
use App\Models\System\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class BeautifyEmail implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $log;

    public array $envelope;

    public int $retryAfter = 60;

    public BetterEmails $eml;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(BetterEmails $eml, array $log, array $envelope)
    {
        $this->log = $log;
        $this->envelope = $envelope;
        $this->eml = $eml;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (Helpers::isSystemFeatureEnabled('better-emails')) {

            $settings = Settings::firstOrFail();
            $email_details = [
                'theme' => $this->eml->theme,
                'include' => [
                    'report_metadata' => $this->eml->report_metadata,
                    'message_history' => $this->eml->message_history,
                ],
                'title' => $this->eml->title,
                'description' => $this->eml->description,
                'envelope' => $this->envelope,
                'log' => $this->log,
                'subject' => $this->eml->subject,
                'logo' => [
                    'src' => $this->eml->logo,
                    'alt' => $this->eml->logo_alt,
                    'link' => $this->eml->logo_link,
                ],
                'button' => [
                    'text' => $this->eml->button_text,
                    'link' => $this->eml->button_link,
                ],
                'canspam' => [
                    'address' => $settings->better_emails_canspam_address ?? '',
                    'address2' => $settings->better_emails_canspam_address2 ?? '',
                    'city' => $settings->better_emails_canspam_city ?? '',
                    'state' => $settings->better_emails_canspam_state ?? '',
                    'postal' => $settings->better_emails_canspam_postal ?? '',
                    'country' => $settings->better_emails_canspam_country ?? '',
                    'email' => $settings->better_emails_canspam_email ?? '',
                    'phone' => $settings->better_emails_canspam_phone ?? '',
                    'company' => $settings->better_emails_canspam_company ?? '',
                ],
            ];

            foreach (json_decode($this->eml->recipients) as $recipient) {
                $email_details['unsubscribe_link'] = URL::signedRoute('email-unsubscribe', ['eid' => $this->eml->id, 'email' => $recipient]);
                Mail::to($recipient)->queue(new PrettyLog($email_details));
            }
        }
    }
}
