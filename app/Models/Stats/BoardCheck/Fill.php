<?php

namespace App\Models\Stats\BoardCheck;

use App\Models\BoardCheckItem;
use App\Models\Stats\Stat;
use App\Models\System\Settings;

class Fill extends Stat
{
    public function insertBoardCheckItems(): void
    {
        $settings = Settings::first();

        if (! is_null($settings)) {
            foreach ($this->results as $result) {
                // the msgId is bigger than our last updated,
                // so we make sure not to reimport old numbers
                if ($result->msgId > $settings->board_check_starting_msgId) {
                    $bci = BoardCheckItem::where('msgId', $result->msgId)->first();

                    if (is_null($bci)) { // not an existing entry
                        if (! is_null($result->callId) || ! is_null($result->savedCallID)) { // it has referenced callId
                            $b = new BoardCheckItem;
                            $b->msgId = $result->msgId;
                            $b->callId = $result->callId ?? $result->savedCallID;
                            $b->save();
                        }
                    }
                }
            }
        }
    }

    public function validateParams(): bool
    {
        if (
            array_key_exists('msgId', $this->parameters) // ISCallId must be included
            && (int) $this->parameters['msgId'] > 0  // ISCallId must be greater than 0
        ) {
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
                m.msgId,
                m.cltId,
                m.callId,
                m.savedCallID,
                c.ClientName,
                c.ClientNumber,
                c.BillingCode,
                m.Stamp,
                m.Urgent,
                m.Special,
                m.Held,
                m.Discarded,
                m.Delivered,
                m.DeliveredStamp,
                m.Taken,
                m.ANI,
                m.[Index],
                m.Summary,
                m.agtId,
                a.[Name] as [AgentName],
                a.Initials as [AgentInitials]
            from msgmessages m
                left join cltclients c on c.cltid = m.cltid
                left join agtAgents a on a.agtid = m.agtid
            where
                m.msgid >= ?
                and m.Voice = 0
                and a.[Name] is not null
                and ( datalength(replace(replace(cast(m.Summary as nvarchar(max)), char(13), ''), char(10), '')) > 0
            or datalength(replace(replace(cast(m.[Index] as nvarchar(max)), char(13), ''), char(10), '')) > 0 )
            order by m.stamp asc;
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
