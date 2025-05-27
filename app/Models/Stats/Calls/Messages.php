<?php

namespace App\Models\Stats\Calls;

use StdClass;

class Messages extends Call
{
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
                ,msgMessages.Urgent
                ,msgMessages.Delivered
                ,msgMessages.DeliveredStamp
                ,msgMessages.Voice
                ,msgMessages.Played
                ,msgMessages.Urgent
                ,msgMessages.Discarded
                ,msgMessages.Exported
                ,msgMessages.Archived
                ,msgMessages.[Sent]
                ,msgMessages.Held
                ,msgMessages.copiedFrom
                ,msgMessages.forwardedFrom
                ,msgMessages.Special
                ,msgMessages.MsgOrigin
                ,agtAgents.[Name]
                ,agtAgents.Initials
                ,msgMessages.Summary
            from statMesgTaken
            left join agtagents on agtagents.agtid = statMesgTaken.agtId
            left join cltClients on cltClients.cltID = statMesgTaken.cltId
            left join msgMessages on msgMessages.msgId = statMesgTaken.msgId
            where statMesgTaken.callid = ? order by msgMessages.[Stamp] desc;
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
