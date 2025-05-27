<?php

namespace App\Models\Stats\Agents;

use App\Models\Stats\Stat;

class Listing extends Stat
{
    public function validateParams(): bool
    {
        return true;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select agtId, Stamp, Name, Initials from agtActiveAgents where DeleteAgent = 0 order by [Name] asc;
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
