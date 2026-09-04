<?php

declare(strict_types=1);

namespace App\Livewire\System\Observability;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\System\Settings;
use App\Services\Observability\ObservabilityConfig;
use App\Services\Observability\TraceEndpointProbe;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin form for opt-in OpenTelemetry tracing exported to Grafana Tempo.
 *
 * The auth token is write-only for the same reason as the GlitchTip DSN:
 * public Livewire properties are serialized into the browser snapshot.
 */
class Tracing extends Component
{
    use AuthorizesSystemComponent;

    public bool $enabled = false;

    public string $endpoint = '';

    public string $protocol = 'http/protobuf';

    public string $authUsername = '';

    /** Write-only. Always blank on mount. */
    public string $authToken = '';

    public string $serviceName = '';

    public float $sampleRate = 0.1;

    public bool $dbSpansEnabled = false;

    public int $dbSlowQueryMs = 0;

    #[Locked]
    public bool $hasAuthToken = false;

    #[Locked]
    public bool $envOverridden = false;

    #[Locked]
    public ?string $probeStatus = null;

    #[Locked]
    public ?string $probeMessage = null;

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

        $settings->observability_tracing_enabled = $this->enabled;
        $settings->observability_tracing_endpoint = $this->endpoint ?: null;
        $settings->observability_tracing_protocol = $this->protocol ?: null;
        $settings->observability_tracing_auth_username = $this->authUsername ?: null;
        $settings->observability_tracing_service_name = $this->serviceName ?: null;
        $settings->observability_tracing_sample_rate = $this->sampleRate;
        $settings->observability_tracing_db_spans_enabled = $this->dbSpansEnabled;
        $settings->observability_tracing_db_slow_query_ms = $this->dbSlowQueryMs;

        // Blank keeps the stored secret; the cast encrypts on write.
        if (filled($this->authToken)) {
            $settings->observability_tracing_auth_token = $this->authToken;
        }

        $settings->save();

        ObservabilityConfig::flush();

        $this->authToken = '';
        $this->hydrateFromSettings();
        $this->dispatch('saved');

        // Enabling without a reachable collector is the most likely mistake, so
        // check immediately rather than letting spans vanish silently.
        if ($this->enabled) {
            $this->checkEndpoint();
        }
    }

    /**
     * Reports whether anything is actually listening on the OTLP endpoint.
     *
     * Alloy is the recommended collector but it is the operator's own
     * infrastructure — this application never assumes it is already running.
     */
    public function checkEndpoint(?TraceEndpointProbe $probe = null): void
    {
        $this->authorize($this->requiredCapability()->value);

        // An admin-supplied endpoint means the server makes an outbound request
        // to a host of their choosing; rate limit it so it cannot be used to
        // sweep ports.
        $key = 'otlp-probe:'.(auth()->id() ?? 'guest');

        if (! RateLimiter::attempt($key, 5, fn () => null, 60)) {
            $this->probeStatus = TraceEndpointProbe::ERROR;
            $this->probeMessage = 'Too many checks. Try again in a minute.';

            return;
        }

        $probe ??= app(TraceEndpointProbe::class);

        $tracing = ObservabilityConfig::tracing();

        // Probe what is in the form, so it can be tested before saving.
        if (filled($this->endpoint)) {
            $tracing['exporter']['endpoint'] = $this->endpoint;
        }

        $result = $probe->probe($tracing);

        $this->probeStatus = $result['status'];
        $this->probeMessage = $result['message'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'enabled' => ['boolean'],
            'endpoint' => ['nullable', 'string', 'url', 'max:255'],
            'protocol' => ['required', 'in:http/protobuf,http/json'],
            'authUsername' => ['nullable', 'string', 'max:255'],
            'authToken' => ['nullable', 'string', 'max:4096'],
            'serviceName' => ['nullable', 'string', 'max:100'],
            'sampleRate' => ['numeric', 'min:0', 'max:1'],
            'dbSpansEnabled' => ['boolean'],
            'dbSlowQueryMs' => ['integer', 'min:0', 'max:60000'],
        ];
    }

    private function hydrateFromSettings(): void
    {
        $settings = Settings::first();
        $resolved = ObservabilityConfig::tracing();

        $this->enabled = (bool) ($settings?->observability_tracing_enabled ?? false);
        $this->endpoint = (string) ($settings?->observability_tracing_endpoint
            ?? $resolved['exporter']['endpoint']);
        $this->protocol = (string) ($settings?->observability_tracing_protocol ?? 'http/protobuf');
        $this->authUsername = (string) ($settings?->observability_tracing_auth_username ?? '');
        $this->serviceName = (string) ($settings?->observability_tracing_service_name ?? '');
        $this->sampleRate = (float) ($settings?->observability_tracing_sample_rate ?? 0.1);
        $this->dbSpansEnabled = (bool) ($settings?->observability_tracing_db_spans_enabled ?? false);
        $this->dbSlowQueryMs = (int) ($settings?->observability_tracing_db_slow_query_ms ?? 0);

        $this->hasAuthToken = filled($settings?->observability_tracing_auth_token);
        $this->envOverridden = (bool) ($resolved['overridden_by_env'] ?? false);
    }

    public function render(): View
    {
        return view('livewire.system.observability.tracing');
    }
}
