<?php

declare(strict_types=1);

namespace App\Services\Azure;

use App\Models\DataSource;

/**
 * The Entra app registration the token watcher authenticates as.
 *
 * Read from the single `data_sources` row, where every other third-party
 * credential lives; the secret is decrypted by the model's EncryptedSerialized
 * cast, so this works in plaintext.
 */
final class GraphCredentials
{
    public function __construct(
        public readonly ?string $tenantId,
        public readonly ?string $clientId,
        public readonly ?string $clientSecret,
        public readonly bool $enabled,
    ) {}

    public static function fromDataSource(): self
    {
        $datasource = DataSource::firstOrNew();

        return new self(
            tenantId: $datasource->azure_tenant_id,
            clientId: $datasource->azure_client_id,
            clientSecret: $datasource->azure_client_secret,
            enabled: (bool) $datasource->azure_enabled,
        );
    }

    /**
     * Whether all three values needed for the client credentials flow are present.
     * Separate from enabled(): a tenant can be fully configured with sweeps
     * switched off.
     */
    public function configured(): bool
    {
        return filled($this->tenantId)
            && filled($this->clientId)
            && filled($this->clientSecret);
    }

    /**
     * @throws GraphException
     */
    public function requireConfigured(): void
    {
        if (! $this->configured()) {
            throw new GraphException(
                'Entra ID is not configured. Add the tenant ID, client ID and client '
                .'secret on System -> Integrations.'
            );
        }
    }

    /**
     * Identifies this credential set for cache keys, so rotating the secret or
     * pointing at another tenant cannot serve a token issued for the old one.
     */
    public function fingerprint(): string
    {
        return sha1(($this->tenantId ?? '').'|'.($this->clientId ?? '').'|'.($this->clientSecret ?? ''));
    }
}
