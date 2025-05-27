<?php

namespace App\Models\Stats\Calls;

use App\Models\Stats\Stat;
use StdClass;

class Keywords extends Stat
{

    public function validateParams(): bool
    {
        if (!isset($this->parameters['ISCallId'])) {
            return false;
        }
        if (!isset($this->parameters['savedCallId'])) {
            return false;
        }
        return true;
    }

    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
                msgmessages.msgId,
                msgmessagekeywords.Response as [Keyword],
                msgmessagekeywords.Value
            from msgMessageKeywords
            left join msgmessages on msgmessages.msgid = msgMessageKeywords.msgid
            where msgmessages.callid = ? or msgmessages.savedCallId = ?
            order by msgmessagekeywords.Response, msgmessagekeywords.Value ASC
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
