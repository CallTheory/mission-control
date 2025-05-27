<?php

namespace App\Models\Stats\Subjects;

use App\Models\Stats\Stat;

class Overview extends Stat
{
    public function validateParams(): bool
    {
        return true; //no parameters
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            use [intelligent];

               SET NOCOUNT OFF


            -- SQL 2016+
            --DROP TABLE IF EXISTS #AmtelcoDatabaseAnalysis;

            -- SQL 2012 or earlier
            if object_id('tempdb.dbo.#Overview', 'U') is not null drop table #Overview;

            create table #Overview
            (
                [Item] nvarchar(max),
                [Count] bigint
            );
            insert into #Overview select N'ContactSubjects' as [Item], count(subid) as [Count] from dirSubjects;
            insert into #Overview select N'Listings' as [Item], count(listid) as [Count] from dirListings;
            insert into #Overview select N'ContactEmail' as [Item], count(listid) as [Count] from dirContactEmail;
            insert into #Overview select N'ContactCisco' as [Item], count(listid) as [Count] from dirContactCisco;
            insert into #Overview select N'ContactWctp' as [Item], count(listid) as [Count] from dirContactWctp;
            insert into #Overview select N'ContactFax' as [Item], count(listid) as [Count] from dirContactFax;
            insert into #Overview select N'ContactPhone' as [Item], count(listid) as [Count] from dirContactPhone;
            insert into #Overview select N'ContactSecureMessaging' as [Item], count(listid) as [Count] from dirContactSecureMessaging;
            insert into #Overview select N'ContactSms' as [Item], count(listid) as [Count] from dirContactSms;
            insert into #Overview select N'ContactSnpp' as [Item], count(listid) as [Count] from dirContactSnpp;
            insert into #Overview select N'ContactTapPager' as [Item], count(listid) as [Count] from dirContactTapPager;
            insert into #Overview select N'ContactVocera' as [Item], count(listid) as [Count] from dirContactVocera;
            insert into #Overview select N'OnCallSchedules' as [Item], count(ocschedID) as [Count] from dirOnCallSchedules;
            insert into #Overview select N'OnCallShifts' as [Item], count(shiftid) as [Count] from dirOnCallShifts;


            select [Item], [Count] from #Overview order by [Item] ASC;

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
