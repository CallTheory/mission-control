<?php

namespace App\Models\Stats\Calls;

use App\Models\Stats\Stat;

class RecentCalls extends Stat
{
    public function validateParams(): bool
    {
        return true;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select top 25
                statCallStart.callId as [CallId]
                ,cltClients.ClientNumber as [ClientNumber]
                ,cltClients.ClientName as [ClientName]
                ,statCallStart.Stamp as [CallStart]
                ,statCallStart.ANI as [CallerANI]
                ,statCallEnd.[Kind]
                ,statCallEnd.[SkillName]
                ,agtAgents.Name as [AgentName]
                ,agtAgents.Initials as [AgentInitials]
            from statCallEnd
            left join statCallStart on statCallStart.callId = statCallEnd.callId
            left join cltClients on statCallStart.cltID = cltClients.cltId
            left join agtAgents on statCallEnd.agtId = agtAgents.agtId
            order by statCallStart.Stamp desc
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
