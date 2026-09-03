<?php

declare(strict_types=1);

namespace App\Livewire\System\Observability;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\System\Settings;
use App\Rules\ValidSentryDsn;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\TestEventSender;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Sentry\Dsn;
use Throwable;

/**
 * Admin form for opt-in GlitchTip/Sentry exception reporting.
 *
 * The DSN is write-only: it is never hydrated into a public property, because
 * public Livewire properties are serialized into the component snapshot that
 * is sent to — and echoed back from — the browser. A blank value on save keeps
 * the stored secret, mirroring App\Livewire\System\Integrations\Ringcentral.
 */
class Errors extends Component
{
    use AuthorizesSystemComponent;

    public bool $enabled = false;

    /** Write-only. Always blank on mount. */
    public string $dsn = '';

    public string $environment = '';

    public string $release = '';

    public float $sampleRate = 1.0;

    #[Locked]
    public bool $hasDsn = false;

    /** Host + project id, key masked. Computed server-side. */
    #[Locked]
    public string $dsnPreview = '';

    #[Locked]
    public bool $envOverridden = false;

    #[Locked]
    public ?string $lastTestStatus = null;

    #[Locked]
    public ?string $lastTestAt = null;

    #[Locked]
    public ?string $testResult = null;

    #[Locked]
    public ?string $testError = null;

    protected function requiredCapability(): Capability
    {
        return Capability::SystemObservability;
    }

    public function mount(): void
    {
        $this->hydrateFromSettings();
    }

    public function save(): void
    {
        $this->authorize($this->requiredCapability()->value);

        $this->validate();

        $settings = Settings::first() ?? new Settings;

        // Enabling requires a DSN — either already stored or supplied now.
        if ($this->enabled && blank($this->dsn) && blank($settings->observability_errors_dsn)) {
            throw ValidationException::withMessages([
                'dsn' => 'A DSN is required to enable exception reporting.',
            ]);
        }

        $settings->observability_errors_enabled = $this->enabled;
        $settings->observability_environment = $this->environment ?: null;
        $settings->observability_release = $this->release ?: null;
        $settings->observability_errors_sample_rate = $this->sampleRate;

        // Only overwrite the DSN when a new one was actually entered; the cast
        // encrypts on write, so pass plaintext.
        if (filled($this->dsn)) {
            $settings->observability_errors_dsn = $this->dsn;
        }

        $settings->save();

        ObservabilityConfig::flush();

        $this->dsn = '';
        $this->hydrateFromSettings();
        $this->dispatch('saved');
    }

    public function sendTestEvent(TestEventSender $sender): void
    {
        $this->authorize($this->requiredCapability()->value);

        $this->validate();

        $settings = Settings::first();
        $dsn = filled($this->dsn) ? $this->dsn : $settings?->observability_errors_dsn;

        if (blank($dsn)) {
            $this->testResult = null;
            $this->testError = 'Enter a DSN first, or save one, before sending a test event.';

            return;
        }

        $result = $sender->send(
            (string) $dsn,
            $this->environment ?: null,
            $this->release ?: null,
            auth()->id(),
        );

        if ($result['ok']) {
            $this->testResult = "Test event accepted in {$result['ms']}ms".
                ($result['eventId'] ? " (event {$result['eventId']})" : '').
                '. Note GlitchTip returns success for payloads it later drops, so this '.
                'confirms connectivity and credentials rather than feature support.';
            $this->testError = null;
        } else {
            $this->testResult = null;
            $this->testError = $result['error'] ?? 'Unknown error.';
        }

        if ($settings !== null) {
            $settings->observability_last_test_at = now();
            $settings->observability_last_test_status = $result['ok'] ? 'ok' : 'failed';
            $settings->save();
            ObservabilityConfig::flush();
            $this->hydrateFromSettings();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'dsn' => ['nullable', 'string', 'max:500', new ValidSentryDsn],
            'environment' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'release' => ['nullable', 'string', 'max:64'],
            'sampleRate' => ['numeric', 'min:0', 'max:1'],
        ];
    }

    private function hydrateFromSettings(): void
    {
        $settings = Settings::first();
        $resolved = ObservabilityConfig::errors();

        $this->enabled = (bool) ($settings?->observability_errors_enabled ?? false);
        $this->environment = (string) ($settings?->observability_environment ?? '');
        $this->release = (string) ($settings?->observability_release ?? '');
        $this->sampleRate = (float) ($settings?->observability_errors_sample_rate ?? 1.0);

        $storedDsn = $settings?->observability_errors_dsn;
        $this->hasDsn = filled($storedDsn);
        $this->dsnPreview = $this->maskDsn($storedDsn);

        $this->envOverridden = (bool) ($resolved['overridden_by_env'] ?? false);
        $this->lastTestStatus = $settings?->observability_last_test_status;
        $this->lastTestAt = $settings?->observability_last_test_at?->diffForHumans();
    }

    /**
     * "https://••••@glitchtip.example.com/3" — never the key itself.
     */
    private function maskDsn(?string $dsn): string
    {
        if (blank($dsn)) {
            return '';
        }

        try {
            $parsed = Dsn::createFromString($dsn);

            return sprintf(
                '%s://••••@%s/%s',
                $parsed->getScheme(),
                $parsed->getHost(),
                $parsed->getProjectId()
            );
        } catch (Throwable) {
            return '(unparseable DSN stored)';
        }
    }

    public function render(): View
    {
        return view('livewire.system.observability.errors');
    }
}
