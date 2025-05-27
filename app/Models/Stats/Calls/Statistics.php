<?php

namespace App\Models\Stats\Calls;

use StdClass;

class Statistics extends Call
{
    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
                statCallEnd.[selDisc]
                ,statCallEnd.[unselDisc]
                ,statCallEnd.[selRing]
                ,statCallEnd.[unselRing]
                ,statCallEnd.[selTalk]
                ,statCallEnd.[unselTalk]
                ,statCallEnd.[selTalk1]
                ,statCallEnd.[unselTalk1]
                ,statCallEnd.[selTalk2]
                ,statCallEnd.[unselTalk2]
                ,statCallEnd.[selConference]
                ,statCallEnd.[unselConference]
                ,statCallEnd.[selHold]
                ,statCallEnd.[unselHold]
                ,statCallEnd.[selInProgress]
                ,statCallEnd.[unselInProgress]
                ,statCallEnd.[selVoiceMail]
                ,statCallEnd.[unselVoiceMail]
                ,statCallEnd.[selAuto]
                ,statCallEnd.[unselAuto]
                ,statCallEnd.[selOutboundQueue]
                ,statCallEnd.[unselOutboundQueue]
                ,statCallEnd.[selAutoHold]
                ,statCallEnd.[unselAutoHold]
                ,statCallEnd.[selPatch]
                ,statCallEnd.[unselPatch]
            from statCallStart
            left join statCallEnd on statCallStart.callId = statCallEnd.callId
            where statCallStart.callId = ? order by statCallStart.Stamp asc
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
