<?php

namespace App\Models\Stats\Schedule;

use App\Models\Stats\Stat;

class Maintenance extends Stat
{
    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select schId, Scheduled, Action, RecurrenceType, RecurrenceMask, RecurrenceInterval from schschedule where [Action] in (0,1,8,9,10,11,12)
            TSQL);
    }

    public function details(): array|\stdClass|null
    {
        // We want all the objects
        return $this->results;
    }

    public function validateParams(): bool
    {
        // no params
        return true;
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
