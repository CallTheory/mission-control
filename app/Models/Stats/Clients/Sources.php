<?php

namespace App\Models\Stats\Clients;

use App\Models\Stats\Stat;

class Sources extends Stat
{
    public function validateParams(): bool
    {
        if (array_key_exists('all', $this->parameters)) {
            return true;
        } elseif (
            array_key_exists('cltId', $this->parameters) //ISCallId must be included
            && (int) $this->parameters['cltId'] > 0) { //ISCallId must be greater than 0
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        if (array_key_exists('all', $this->parameters)) {
            return trim(<<<'TSQL'
            select [cltId], [Source] from cltSources;
            TSQL);
        }

        return trim(<<<'TSQL'
            select [cltId], [Source] from cltSources where [cltId] = ?;
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
