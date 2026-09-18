<?php

declare(strict_types=1);

namespace App\Livewire\System\AzureTokens;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\System\Settings;
use App\Services\Azure\ExpiryAlerter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Who hears about expiring credentials.
 *
 * The thresholds themselves are not configurable: they are the ones the alert
 * logic tracks per credential to stay idempotent (see ExpiryAlerter), and a
 * threshold that can be edited after the fact makes "already alerted at 14 days"
 * meaningless.
 */
class Alerting extends Component
{
    use AuthorizesSystemComponent;

    public bool $enabled = false;

    public string $recipients = '';

    #[Locked]
    public ?string $saveError = null;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemAzureTokens;
    }

    public function mount(): void
    {
        $settings = Settings::first() ?? new Settings;

        $this->enabled = (bool) ($settings->azure_tokens_alert_enabled ?? false);
        $this->recipients = (string) ($settings->azure_tokens_alert_recipients ?? '');
    }

    /**
     * @return array<int, int>
     */
    public function getThresholdsProperty(): array
    {
        return ExpiryAlerter::THRESHOLDS;
    }

    public function save(): void
    {
        $this->authorize($this->requiredCapability()->value);

        $addresses = ExpiryAlerter::recipients($this->recipients);

        // Written by hand and easy to mistype, so anything that is not an address is
        // reported rather than silently dropped by the splitter at send time.
        $rejected = array_values(array_diff(
            array_filter(array_map('trim', preg_split('/[,;\s]+/', $this->recipients) ?: [])),
            $addresses
        ));

        if ($rejected !== []) {
            throw ValidationException::withMessages([
                'recipients' => 'Not a valid email address: '.implode(', ', $rejected),
            ]);
        }

        if ($this->enabled && $addresses === []) {
            throw ValidationException::withMessages([
                'recipients' => 'At least one recipient is required to enable alerting.',
            ]);
        }

        $settings = Settings::first() ?? new Settings;
        $settings->azure_tokens_alert_enabled = $this->enabled;
        $settings->azure_tokens_alert_recipients = $addresses === [] ? null : implode(', ', $addresses);
        $settings->save();

        $this->recipients = (string) $settings->azure_tokens_alert_recipients;

        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.system.azure-tokens.alerting');
    }
}
