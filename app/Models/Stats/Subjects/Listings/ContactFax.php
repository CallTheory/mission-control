<?php

namespace App\Models\Stats\Subjects\Listings;

use App\Models\Stats\Stat;

class ContactFax extends Stat
{
    public function tsql(): string
    {
        return trim(<<<'TSQL'
             select
                dirlistings.listId,
                dirContactFax.[Name] as [MethodName],
                dirsubjects.[Name] as [DirectorySubject],
                dirviews.[Name] as [View],
                dirContactFax.Phone as [Result]
            from dirContactFax
            left join dirlistings  on dirlistings.listid = dirContactFax.listid
            left join dirsubjects on dirlistings.subid = dirsubjects.subid
            left join dirViews on dirviews.subId = dirsubjects.subId
            where Phone like CONCAT ('%', ?, '%')
            TSQL);
    }

    public function validateParams(): bool
    {
        if (isset($this->parameters[0])) {
            return true;
        }

        return false;
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
