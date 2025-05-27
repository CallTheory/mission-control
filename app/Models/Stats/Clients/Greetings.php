<?php

namespace App\Models\Stats\Clients;

use App\Models\Stats\Stat;

class Greetings extends Stat
{

    public function validateParams(): bool
    {
        if(array_key_exists('greetingID', $this->parameters) && (int) $this->parameters['greetingID'] > 0) {
            return true;
        }
        if (array_key_exists('all', $this->parameters)) {
            return true;
        } elseif (
            array_key_exists('cltId', $this->parameters) //ISCallId must be included
            && (int) $this->parameters['cltId'] > 0) { //ISCallId must be greater than 0
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        if(array_key_exists('greetingID', $this->parameters))
        {
            return trim(<<<'TSQL'
            select
                cltGreetings.greetingID,
                cltClients.ClientNumber,
                cltClients.ClientName,
                cltClients.BillingCode,
                cltGreetingStreams.Greeting,
                cltGreetings.Format,
                cltGreetings.[Name] as [GreetingName],
                cltGreetingStreams.[Stamp]
            from cltGreetings
            left join cltGreetingStreams on cltGreetingStreams.greetingId = cltGreetings.greetingID
            left join cltClients on cltClients.cltId = cltGreetings.cltId
            where cltGreetings.greetingID = ?
            TSQL);
        }
        if (array_key_exists('all', $this->parameters)) {
            return trim(<<<'TSQL'
            select
                cltGreetings.greetingID,
                 cltClients.ClientNumber,
                cltClients.ClientName,
                cltClients.BillingCode,
                cltGreetings.[Name] as [GreetingName],
                cltGreetingStreams.[Stamp]
            from cltGreetings
            left join cltGreetingStreams on cltGreetingStreams.greetingId = cltGreetings.greetingID
            left join cltClients on cltClients.cltId = cltGreetings.cltId
            order by cltGreetings.cltID asc, cltGreetingStreams.Stamp desc
            TSQL);
        }

        return trim(<<<'TSQL'
            select
                cltGreetings.greetingID,
                cltClients.ClientNumber,
                cltClients.ClientName,
                cltClients.BillingCode,
                cltGreetings.[Name] as [GreetingName],
                cltGreetingStreams.[Stamp]
            from cltGreetings
            left join cltGreetingStreams on cltGreetingStreams.greetingId = cltGreetings.greetingID
            left join cltClients on cltClients.cltId = cltGreetings.cltId
            where cltClients.cltId = ?
            order by cltGreetings.cltID asc, cltGreetingStreams.Stamp desc
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
