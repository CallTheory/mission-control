<?php

namespace App\Models\Stats\Agents;

use App\Models\Stats\Stat;

class AgentCalls extends Stat
{

    public function validateParams(): bool
    {
        if (
            array_key_exists('agtId', $this->parameters) //agtId must be included
            && strlen($this->parameters['agtId']) > 0 //the length of agent_name must be greater than 0
        ) {
            return true;
        }

        return false;
    }

    public function __construct($parameters)
    {
        parent::__construct($parameters);
    }

    public function tsql(): string
    {

        //technically this breaks down if they change stations mid-call - it might not show that?
        return trim(<<<'TSQL'
           select
            sac.id,
            sac.callID,
            sac.agtID,
            sac.Stamp,
            sac.Duration,
            (select top 1 stationNumber from statCallTracker sct where sct.callID = sac.callID  and sct.agtId = ?) As StationId,
            (select top 1 StationType from statCallTracker sct where sct.callID = sac.callID  and sct.agtId = ?) As StationType
        from statAgentCalls sac
        where sac.agtId = ? and sac.[Stamp] between ? and ?
        order by sac.callID desc
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
