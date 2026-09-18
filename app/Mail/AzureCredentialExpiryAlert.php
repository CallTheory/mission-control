<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * One digest of the Entra credentials that have just crossed an alert threshold.
 *
 * A digest rather than a message per credential: a tenant's secrets are usually
 * created in batches and therefore expire in batches, and four separate emails
 * about the same app registration is how an alert gets filtered.
 */
class AzureCredentialExpiryAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Plain arrays rather than models: the queue serializes this payload, and a
     * credential row can be swept away between queueing and sending.
     *
     * @param  array<int, array<string, mixed>>  $credentials
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public array $credentials,
        public array $recipients,
    ) {
        $this->queue = 'outbound-email';
    }

    public function build(): static
    {
        return $this->to($this->recipients)
            ->subject($this->subjectLine())
            ->markdown('emails.azure.credential-expiry', [
                'dashboardUrl' => secure_url('/system/azure-tokens'),
            ]);
    }

    /**
     * Says what happened in the subject line, because the worst case -- something
     * has already expired -- should not need the body to be opened.
     *
     * Not named subject(): Mailable already has one, and it takes an argument.
     */
    public function subjectLine(): string
    {
        $expired = count(array_filter(
            $this->credentials,
            fn (array $credential): bool => ($credential['days_remaining'] ?? 1) < 0,
        ));

        $count = count($this->credentials);

        if ($expired > 0) {
            return 'Azure credentials expired: '.$expired.' of '.$count.' need attention';
        }

        $soonest = min(array_map(
            fn (array $credential): int => (int) ($credential['days_remaining'] ?? 0),
            $this->credentials,
        ));

        return 'Azure credentials expiring: '.$count.' within '.$soonest.' day'.($soonest === 1 ? '' : 's');
    }
}
