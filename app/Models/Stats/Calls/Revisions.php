<?php

namespace App\Models\Stats\Calls;

use StdClass;

class Revisions extends Call
{

    public function __construct(array $parameters)
    {
        parent::__construct($parameters);
    }

    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
                cltClients.[ClientName]
                ,cltClients.ClientNumber
                ,cltClients.[BillingCode]
                ,msgMessages.OriginalClientNumber
                ,msgMessages.OriginalBillingCode
                ,msgMessages.[msgId]
                ,msgMessages.[Index]
                ,msgMessages.[XmlMessage]
            from msgMessages
            left join cltClients on cltClients.cltID = msgmessages.cltId
            where msgMessages.callid = ? or msgMessages.savedCallId = ? order by msgMessages.[Stamp] desc;
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
