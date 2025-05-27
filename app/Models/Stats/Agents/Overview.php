<?php

namespace App\Models\Stats\Agents;

use App\Models\Stats\Stat;

class Overview extends Stat
{
    public function validateParams(): bool
    {
        if (
            array_key_exists('start_date', $this->parameters)
            && array_key_exists('end_date', $this->parameters)
        ) {
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            declare @STARTDATE datetime = ?
            declare @ENDDATE datetime = ?

            SELECT A.agtId, A.Name, A.Initials, A.agtType
            ,ISNULL((SELECT SUM(AC.Duration)
            FROM statAgentCalls AC
            WHERE
            AC.agtID = AA.agtID
            AND (AC.Stamp BETWEEN @STARTDATE
            AND @ENDDATE)
            ),0)/10000000 AS AgentDuration
            ----------
            ,ISNULL((SELECT SUM(DATEDIFF("s", '1/1/1900',AST.Duration))
            FROM statAgentSupervisorTime AST
            WHERE
            AST.agtId = AA.agtId
            AND (AST.Stamp BETWEEN @STARTDATE AND @ENDDATE)
            ),0)/10000000 AS SuperDuration,A.LockedOut
            ----------
            -- this count can be bigger than total calls not grouped by Agt
            -- because nore than one agt can be on a single call
            ,ISNULL(
            (SELECT COUNT(DISTINCT CE.callId)
            FROM statAgentCalls AC
            JOIN statCallEnd CE ON CE.callId = AC.callID
            JOIN cltClients C ON C.cltId = CE.cltId
            WHERE AC.agtId = AA.agtId
            AND CE.Stamp BETWEEN @STARTDATE AND @ENDDATE
            AND (CE.Kind = 1 OR CE.Kind =2)
            AND C.ClientNumber <> 0
            ),0)
            AS Calls
            ----------
            ,ISNULL((SELECT COUNT(DISTINCT D.dispID)
            FROM statDispatchAdded D
            WHERE
            D.agtId = AA.agtId
            AND (D.[Timestamp] BETWEEN @STARTDATE AND  @ENDDATE)
            ),0) AS Dispatches
            ----------
            ,ISNULL((SELECT COUNT(DISTINCT SE.Id)
            FROM statClientMaintenance SE
            WHERE
            SE.agtId = AA.agtId
            AND (SE.Stamp > @STARTDATE
            AND SE.Stamp < @ENDDATE
            AND  SE.[Type] = 1)),0) AS ScriptEdits
            ----------
            ,ISNULL((SELECT SUM(SE.Duration)
            FROM statClientMaintenance SE
            WHERE
            SE.agtId = AA.agtId
            AND (SE.Stamp BETWEEN @STARTDATE AND @ENDDATE
            AND SE.[Type] = 1)
            ),0)/10000000 AS SEDuration
            ----------
            ,ISNULL((SELECT COUNT(DISTINCT DS.Id)
            FROM statDirectorySetup DS
            WHERE
            DS.agtId = AA.agtId
            AND (DS.Stamp BETWEEN @STARTDATE
            AND @ENDDATE)
            ),0) AS DirSetups
            ----------
            ,ISNULL((SELECT SUM(DATEDIFF("s", '1/1/1900',DS.Duration))
            FROM statDirectorySetup DS
            WHERE
            DS.agtId = AA.agtId
            AND (DS.Stamp BETWEEN @STARTDATE AND @ENDDATE)
            ),0)/10000000 AS DSDuration
            ----------
            ,ISNULL((SELECT COUNT(DO.ID)
            FROM statDialouts DO
            WHERE
            DO.agtId = AA.agtId
            AND (DO.[Timestamp] BETWEEN @STARTDATE AND @ENDDATE
            AND DO.Duration > 0)
            ),0) AS Dials
            ----------
            ,ISNULL((SELECT SUM(DO.Duration)
            FROM statDialouts DO
            WHERE
            DO.agtId = AA.agtId
            AND (DO.[Timestamp] BETWEEN @STARTDATE AND @ENDDATE)
            ),0) AS DialDur
            FROM agtActiveAgents AA
            JOIN agtAgents A ON AA.agtId = A.agtId
            ORDER BY [Calls] DESC;

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
