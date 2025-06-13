<?php

namespace App\Models\Stats\Agents;

use App\Models\Stats\Stat;

class AgentModeChange extends Stat
{
    public function validateParams(): bool
    {
        if (
            array_key_exists('agtId', $this->parameters) // agtId must be included
            && strlen($this->parameters['agtId']) > 0 // the length of agent_name must be greater than 0
        ) {
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
           select id, Stamp, agtId, Name, Initials, StationId, StationType, Mode, Duration, Reason, acdGroups
           from statAgentModeChange
           where agtId = ? and [Stamp] between ? and ?
           order by [Stamp] desc;
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
