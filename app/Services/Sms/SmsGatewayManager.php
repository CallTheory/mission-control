<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\SmsProvider;
use App\Models\DataSource;
use App\Services\Sms\Gateways\BandwidthGateway;
use App\Services\Sms\Gateways\CommioGateway;
use App\Services\Sms\Gateways\TwilioGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves carrier gateways, and answers "which carrier is the default".
 *
 * Deliberately NOT bound as a singleton: gateways cache the DataSource row they
 * were built from, so a fresh manager per request (and per queued job) is what
 * makes a credential edit take effect without restarting Horizon.
 */
class SmsGatewayManager
{
    /**
     * @var array<class-string<SmsGateway>>
     */
    private const GATEWAYS = [
        SmsProvider::Twilio->value => TwilioGateway::class,
        SmsProvider::Bandwidth->value => BandwidthGateway::class,
        SmsProvider::Commio->value => CommioGateway::class,
    ];

    /**
     * @var array<string, SmsGateway>
     */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    /**
     * The gateway for a carrier, or the system default when given nothing.
     */
    public function gateway(SmsProvider|string|null $provider = null): SmsGateway
    {
        if ($provider === null) {
            return $this->gateway($this->defaultProvider());
        }

        $key = $provider instanceof SmsProvider ? $provider->value : $provider;

        if (! isset(self::GATEWAYS[$key])) {
            throw new InvalidArgumentException("Unknown SMS provider [{$key}].");
        }

        return $this->resolved[$key] ??= $this->container->make(self::GATEWAYS[$key]);
    }

    /**
     * The carrier used by any number that has not been assigned one.
     */
    public function defaultProvider(): SmsProvider
    {
        return SmsProvider::tryFromKey(DataSource::first()?->sms_default_provider)
            ?? SmsProvider::fallback();
    }

    public function default(): SmsGateway
    {
        return $this->gateway($this->defaultProvider());
    }

    /**
     * Every gateway, keyed by provider value, in enum order.
     *
     * @return array<string, SmsGateway>
     */
    public function all(): array
    {
        $gateways = [];

        foreach (SmsProvider::cases() as $provider) {
            $gateways[$provider->value] = $this->gateway($provider);
        }

        return $gateways;
    }

    /**
     * The gateways that can actually send right now.
     *
     * @return array<string, SmsGateway>
     */
    public function configured(): array
    {
        return array_filter($this->all(), fn (SmsGateway $gateway): bool => $gateway->isConfigured());
    }
}
