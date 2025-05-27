<?php

namespace App\Models\Stats\Calls;

use StdClass;

class Agents extends Call
{
    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select distinct
                agtAgents.agtId
                ,agtAgents.Name
               ,agtAgents.Initials
            from agtAgents
                left join statCallTracker on agtAgents.agtId = statCallTracker.agtId
            where statCallTracker.callid = ? order by agtAgents.[Name] asc
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
