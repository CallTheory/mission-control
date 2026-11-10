<?php

declare(strict_types=1);

namespace App\Services\Faxing;

use App\Models\DataSource;
use App\Models\RingCentralSender;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * The RingCentral extensions faxes can be sent through.
 *
 * Always the primary extension (the data source's JWT), plus any enabled additional
 * senders. With no additional senders this is a pool of one, and sending behaves exactly
 * as it did before pools existed.
 */
class RingCentralSenderPool
{
    /**
     * The extensions eligible to send, primary first.
     *
     * @return list<RingCentralExtension>
     */
    public function extensions(DataSource $datasource): array
    {
        $extensions = [];

        if ($primary = $this->primary($datasource)) {
            $extensions[] = $primary;
        }

        foreach ($this->rows(enabledOnly: true) as $row) {
            if (filled($row->jwt_token)) {
                $extensions[] = $row->toExtension();
            }
        }

        return $extensions;
    }

    /**
     * Look an extension up by key, disabled ones included.
     *
     * A fax sent through a sender that has since been disabled still lives in that
     * extension's message store, so polling or resending it needs those credentials. A
     * null key is the primary: that is every fax recorded before senders existed.
     */
    public function find(DataSource $datasource, ?string $key): ?RingCentralExtension
    {
        if ($key === null || $key === '' || $key === RingCentralExtension::PRIMARY) {
            return $this->primary($datasource);
        }

        foreach ($this->rows(enabledOnly: false) as $row) {
            if ($row->senderKey() === $key && filled($row->jwt_token)) {
                return $row->toExtension();
            }
        }

        return null;
    }

    private function primary(DataSource $datasource): ?RingCentralExtension
    {
        $jwt = $datasource->ringcentral_jwt_token;

        return filled($jwt) ? new RingCentralExtension(RingCentralExtension::PRIMARY, 'Primary', (string) $jwt) : null;
    }

    /**
     * @return iterable<RingCentralSender>
     */
    private function rows(bool $enabledOnly): iterable
    {
        try {
            return RingCentralSender::query()
                ->when($enabledOnly, fn ($query) => $query->where('enabled', true))
                ->orderBy('sort')
                ->orderBy('id')
                ->get();
        } catch (QueryException $e) {
            // The table ships in an earlier deploy than this code, but if it is missing the
            // primary extension must keep sending exactly as before rather than every fax
            // failing on a query.
            Log::warning('RingCentralSenderPool: additional senders unavailable: '.$e->getMessage());

            return [];
        }
    }
}
