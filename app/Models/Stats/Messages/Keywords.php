<?php

namespace App\Models\Stats\Messages;

use App\Models\Stats\Stat;

class Keywords extends Stat
{
    public function validateParams(): bool
    {
        return true; //no parameters to validate
    }
    public function details(): array|null
    {
        return $this->results ?? null;
    }
    public function tsql(): string
    {
        return trim(<<<'TSQL'
           select distinct(Response) as [Keywords] from msgMessageKeywords;
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
