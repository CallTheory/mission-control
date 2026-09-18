<?php

declare(strict_types=1);

namespace App\Livewire\System\Wctp;

use App\Enums\Capability;
use App\Enums\SmsProvider;
use App\Livewire\Concerns\AuthorizesWctpSection;
use App\Services\Sms\SmsGatewayManager;
use App\Services\WctpService;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The gateway page: the endpoint WCTP clients post to, what it supports, and a
 * panel for sending a test message through a chosen carrier.
 */
class Gateway extends Component
{
    use AuthorizesWctpSection;

    public string $testMessage = '';

    public string $testRecipient = '';

    public string $testProvider = '';

    public string $testResult = '';

    public bool $showTestPanel = false;

    public string $wctpEndpoint = '';

    protected function wctpCapability(): Capability
    {
        return Capability::WctpManage;
    }

    public function mount(): void
    {
        $this->authorizeWctpSection();

        // The endpoint WCTP clients post to (at root /wctp).
        $this->wctpEndpoint = url('/wctp');

        $this->testProvider = $this->defaultProvider()->value;
    }

    /**
     * Every carrier, with whether it can send right now. The gateway is usable as
     * soon as one of them can.
     *
     * @return array<int, array{key: string, label: string, configured: bool, is_default: bool}>
     */
    public function carriers(): array
    {
        try {
            $gateways = app(SmsGatewayManager::class);
            $default = $gateways->defaultProvider();

            return array_map(fn (SmsProvider $provider): array => [
                'key' => $provider->value,
                'label' => $provider->label(),
                'configured' => $gateways->gateway($provider)->isConfigured(),
                'is_default' => $provider === $default,
            ], SmsProvider::cases());
        } catch (Exception $e) {
            // A missing or unreadable DataSource must not break the page; it just
            // means nothing is configured yet.
            return array_map(fn (SmsProvider $provider): array => [
                'key' => $provider->value,
                'label' => $provider->label(),
                'configured' => false,
                'is_default' => $provider === SmsProvider::fallback(),
            ], SmsProvider::cases());
        }
    }

    /**
     * The carriers a test message can actually be sent through.
     *
     * @return array<string, string>
     */
    public function testProviderOptions(): array
    {
        $options = [];

        foreach ($this->carriers() as $carrier) {
            if ($carrier['configured']) {
                $options[$carrier['key']] = $carrier['label'];
            }
        }

        return $options;
    }

    public function isConfigured(): bool
    {
        return $this->testProviderOptions() !== [];
    }

    public function defaultProvider(): SmsProvider
    {
        try {
            return app(SmsGatewayManager::class)->defaultProvider();
        } catch (Exception $e) {
            return SmsProvider::fallback();
        }
    }

    public function sendTestMessage(): void
    {
        $this->authorizeWctpSection();

        $this->validate([
            'testRecipient' => 'required|regex:/^[0-9]{10,15}$/',
            'testMessage' => 'required|min:1|max:160',
            'testProvider' => 'required|in:'.implode(',', array_keys($this->testProviderOptions())),
        ]);

        try {
            $gateway = app(SmsGatewayManager::class)->gateway($this->testProvider);

            $result = $gateway->sendSms($this->testRecipient, $this->testMessage, [
                'messageId' => uniqid('test_'),
            ]);

            $this->testResult = $result['success']
                ? "✓ Test message sent via {$gateway->provider()->label()}! Message ID: ".$result['message_sid']
                : "✗ Failed to send test message via {$gateway->provider()->label()}: ".$result['error'];
        } catch (Exception $e) {
            $this->testResult = '✗ Error: '.$e->getMessage();
        }
    }

    /**
     * An example of the XML a WCTP client would post for this test message, kept so
     * the panel can double as documentation.
     */
    public function buildTestWctpMessage(): string
    {
        return (new WctpService)->createSubmitRequest(
            'TestSender',
            $this->testRecipient,
            $this->testMessage,
            uniqid('test_'),
        );
    }

    public function toggleTestPanel(): void
    {
        $this->showTestPanel = ! $this->showTestPanel;

        if (! $this->showTestPanel) {
            $this->reset(['testMessage', 'testRecipient', 'testResult']);
        }
    }

    public function render(): View
    {
        $this->authorizeWctpSection();

        return view('livewire.system.wctp.gateway');
    }
}
