<?php

namespace App\Models\Stats\Calls;

use StdClass;

class Clients extends Call
{
    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
             select distinct
                cltClients.cltID
               ,cltClients.ClientNumber
               ,cltClients.ClientName
               ,cltClients.BillingCode
             from statCallEnd
                left join statCallTracker on statCallEnd.callid = statCallTracker.callid
                left join cltClients on cltClients.cltID = statCallEnd.cltId
             where statCallTracker.callid = ? order by cltClients.[ClientNumber] asc
            TSQL);
    }

    public function __get($key)
    {
        if (isset($this->results[0])) {
            if (isset($this->results[0]->{$key})) {
                return $this->results[0]->{$key};
            }
        }

        return null;
    }

    public function __isset($key)
    {
        if (isset($this->results[0])) {
            return isset($this->results[0]->{$key});
        }

        return false;
    }
}
