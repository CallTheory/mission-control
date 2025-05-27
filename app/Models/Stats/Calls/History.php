<?php

namespace App\Models\Stats\Calls;

use StdClass;

class History extends Call
{
    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
               msgHistories.histId
               ,msgHistories.msgId
             ,msgHistories.agtId
             ,agtAgents.Name
             ,agtAgents.Initials
             ,msgHistories.Stamp
             ,msgHistories.Disposition
             ,msgHistories.listID
             ,msgHistories.TimezoneOffset
             ,msgHistories.XmlData
             ,msgHistories.callId
            from msgHistories
                left join statMesgTaken on statMesgTaken.msgId = msgHistories.msgId
                left join agtAgents on agtAgents.agtId = msgHistories.agtId
            where statMesgTaken.callid = ? order by msgHistories.[stamp] asc
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
