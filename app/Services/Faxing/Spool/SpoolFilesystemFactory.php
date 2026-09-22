<?php

declare(strict_types=1);

namespace App\Services\Faxing\Spool;

use App\Models\FaxSpoolSource;

/**
 * Builds the right driver for a spool source.
 *
 * Both topologies are first class and neither is going away. Where Amtelco writes into a
 * Samba share on this host, Mission Control is the SMB *server* — there is nothing to
 * connect out to, so that source is a local directory and always will be. Where the
 * folders live on the Intelligent Series server, we are the client, and that is the case
 * the SMB driver replaces a kernel CIFS mount for.
 */
class SpoolFilesystemFactory
{
    /**
     * A driver per (source, provider): for SMB the provider selects the share, because
     * the share *is* the provider in the existing topology.
     */
    public function for(FaxSpoolSource $source, string $provider): SpoolFilesystem
    {
        if (! $source->usesSmb()) {
            return new LocalSpoolFilesystem($source->rootPath());
        }

        $share = $source->shareFor($provider);

        return new SmbSpoolFilesystem(
            host: (string) $source->smb_host,
            shareName: $share['share'],
            prefix: $share['prefix'],
            username: (string) $source->smb_username,
            password: (string) $source->smb_password,
            domain: (string) ($source->smb_domain ?? ''),
            requestTimeout: $source->timeout_seconds ?: 15,
            sessionSeconds: (int) config('services.fax.smb_session_seconds', 40),
            minProtocol: $source->min_protocol,
            maxProtocol: $source->max_protocol,
        );
    }

    /**
     * A driver for a source key, falling back to the legacy convention when no row
     * exists — the same rule FaxSpool uses, so paths stay addressable without a database.
     */
    public function forKey(string $sourceKey, string $provider): SpoolFilesystem
    {
        $source = FaxSpoolSource::findByKey($sourceKey);

        return $source === null
            ? new LocalSpoolFilesystem(storage_path("app/{$sourceKey}"))
            : $this->for($source, $provider);
    }
}
