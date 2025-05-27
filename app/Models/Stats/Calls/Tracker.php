<?php

namespace App\Models\Stats\Calls;

use StdClass;

class Tracker extends Call
{
    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
               statCallTracker.id
               ,statCallTracker.Stamp
               ,statCallTracker.CallId
               ,statCallTracker.callType
               ,statCallTracker.callState
               ,statCallTracker.clientNumber
               ,statCallTracker.agtId
               ,agtAgents.Name
               ,agtAgents.Initials
               ,statCallTracker.stationType
               ,statCallTracker.stationNumber
               ,statCallTracker.[type]
               ,statCallTracker.[value]
               ,statCallTracker.lstId
            from statCallTracker
                left join agtAgents on agtAgents.agtId = statCallTracker.agtId
            where statCallTracker.callid = ? order by statCallTracker.[stamp] asc
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
