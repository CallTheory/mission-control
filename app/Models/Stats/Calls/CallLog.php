<?php

namespace App\Models\Stats\Calls;

use App\Models\Stats\Stat;
use Illuminate\Support\Str;

class CallLog extends Stat
{
    public string $tz;
    public $client_number, $ani, $call_type, $agent, $min_duration, $max_duration, $keyword, $keyword_search;
    public $start_date, $end_date;
    public $hasMessages, $hasRecordings, $hasVideo, $hasAny;
    public string $sortBy;

    private $allowed_accounts, $allowed_billing;
    public string $sortDirection;

    public function validateParams(): bool
    {
        $this->parameters['start_date'] = $this->start_date;
        $this->parameters['end_date'] = $this->end_date;

        return true;
    }

    public function __construct($start_date = null, $end_date = null, $tz = 'UTC', $client_number = null,
    $ani = null, $call_type = null, $agent = null, $min_duration = null, $max_duration = null, $keyword = null, $keyword_search = null,
    $sort_by = null, $sort_direction = null, $hasMessages = null, $hasRecordings = null, $hasVideo = null, $hasAny = false,
    $allowed_accounts = '', $allowed_billing = '')
    {
        $this->ani = $ani;
        $this->call_type = $call_type;
        $this->agent = $agent;
        $this->min_duration = $min_duration;
        $this->max_duration = $max_duration;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->client_number = $client_number;
        $this->tz = $tz;
        $this->keyword = $keyword;
        $this->keyword_search = $keyword_search;
        $this->sortBy = $sort_by ?? 'statCallStart.Stamp';
        $this->sortDirection = $sort_direction ?? 'desc';
        $this->hasAny = $hasAny;

        $this->allowed_accounts = $allowed_accounts;
        $this->allowed_billing  = $allowed_billing;;

        if($this->hasAny === true){
            $this->hasRecordings = null;
            $this->hasMessages = null;
            $this->hasVideo = null;
        }
        else{
            $this->hasAny = null;
            $this->hasRecordings = $hasRecordings;
            $this->hasMessages = $hasMessages;
            $this->hasVideo = $hasVideo;
        }
        parent::__construct();
    }

    public function tsql(): string
    {
        if($this->client_number) {
            $client_sql_filter = " and cltClients.ClientNumber = ?\n ";
            $this->parameters['client_number'] = $this->client_number;
        }
        else{
            $client_sql_filter = '';
        }

        if($this->call_type) {
            $calltype_sql_filter = " and statCallEnd.[Kind] = ?\n ";
            $this->parameters['call_type'] = $this->call_type;
        }
        else{
            $calltype_sql_filter = '';
        }

        if($this->agent) {
            $agent_sql_filter = "AND (
                  SELECT STRING_AGG(agtId, ',')
                  FROM (
                      SELECT DISTINCT agt.agtId AS agtId
                      FROM statCallTracker sct
                      JOIN agtAgents agt ON sct.agtId = agt.agtId
                      WHERE sct.callId = statCallStart.callId
                  ) AS distinctAgents
              ) LIKE CONCAT ('%', ?, '%')\n";
            $this->parameters['agent'] = $this->agent;
        }
        else{
            $agent_sql_filter = '';
        }

        if($this->ani) {
            $ani_sql_filter = " and statCallStart.ANI = ?\n ";
            $this->parameters['ani'] = $this->ani;
        }
        else{
            $ani_sql_filter = '';
        }

        if($this->min_duration) {
            $min_duration_sql_filter = " and (
                    statCallEnd.[selDisc] +
                    statCallEnd.[unselDisc] +
                    statCallEnd.[selRing] +
                    statCallEnd.[unselRing] +
                    statCallEnd.[selTalk] +
                    statCallEnd.[unselTalk] +
                    statCallEnd.[selTalk1] +
                    statCallEnd.[unselTalk1] +
                    statCallEnd.[selTalk2] +
                    statCallEnd.[unselTalk2] +
                    statCallEnd.[selConference] +
                    statCallEnd.[unselConference] +
                    statCallEnd.[selHold] +
                    statCallEnd.[unselHold] +
                    statCallEnd.[selInProgress] +
                    statCallEnd.[unselInProgress] +
                    statCallEnd.[selVoiceMail] +
                    statCallEnd.[unselVoiceMail] +
                    statCallEnd.[selAuto] +
                    statCallEnd.[unselAuto] +
                    statCallEnd.[selOutboundQueue] +
                    statCallEnd.[unselOutboundQueue] +
                    statCallEnd.[selAutoHold] +
                    statCallEnd.[unselAutoHold] +
                    statCallEnd.[selPatch] +
                    statCallEnd.[unselPatch] +
                    statCallEnd.[selBridge] +
                    statCallEnd.[unselBridge]
                ) >= ?\n ";
            $this->parameters['min_duration'] = $this->min_duration;
        }
        else{
            $min_duration_sql_filter = '';
        }

        if($this->max_duration) {
            $max_duration_sql_filter = " and (
                    statCallEnd.[selDisc] +
                    statCallEnd.[unselDisc] +
                    statCallEnd.[selRing] +
                    statCallEnd.[unselRing] +
                    statCallEnd.[selTalk] +
                    statCallEnd.[unselTalk] +
                    statCallEnd.[selTalk1] +
                    statCallEnd.[unselTalk1] +
                    statCallEnd.[selTalk2] +
                    statCallEnd.[unselTalk2] +
                    statCallEnd.[selConference] +
                    statCallEnd.[unselConference] +
                    statCallEnd.[selHold] +
                    statCallEnd.[unselHold] +
                    statCallEnd.[selInProgress] +
                    statCallEnd.[unselInProgress] +
                    statCallEnd.[selVoiceMail] +
                    statCallEnd.[unselVoiceMail] +
                    statCallEnd.[selAuto] +
                    statCallEnd.[unselAuto] +
                    statCallEnd.[selOutboundQueue] +
                    statCallEnd.[unselOutboundQueue] +
                    statCallEnd.[selAutoHold] +
                    statCallEnd.[unselAutoHold] +
                    statCallEnd.[selPatch] +
                    statCallEnd.[unselPatch] +
                    statCallEnd.[selBridge] +
                    statCallEnd.[unselBridge]
                ) < ?\n ";
            $this->parameters['max_duration'] = $this->max_duration;
        }
        else{
            $max_duration_sql_filter = '';
        }

        if($this->keyword_search){

            if($this->keyword){
                $keyword_search_sql_filter = " and exists (
            select 1
            from msgMessages
            left join msgMessageKeywords on msgMessages.msgID = msgMessageKeywords.msgID
            where (statCallStart.callId = msgMessages.callid or statCallStart.CallId = msgMessages.savedCallID)
            and msgMessageKeywords.Response = ?
            and msgMessageKeywords.Value like CONCAT ('%', ?, '%'))\n";
                $this->parameters['keyword'] = $this->keyword;
                $this->parameters['keyword_search'] = $this->keyword_search;
            }
            else{
                $keyword_search_sql_filter = " and exists (
            select 1
            from msgMessages
            left join msgMessageKeywords on msgMessages.msgID = msgMessageKeywords.msgID
            where (statCallStart.callId = msgMessages.callid or statCallStart.CallId = msgMessages.savedCallID)
            and msgMessageKeywords.Value like CONCAT ('%', ?, '%'))\n";
                $this->parameters['keyword_search'] = $this->keyword_search;
            }
        }
        else{
            $keyword_search_sql_filter = '';
        }

        if($this->hasAny === true ){
            $has_video_sql_filter = '';
            $has_messages_sql_filter = '';
            $has_recording_sql_filter = '';
        }
        else{

            if($this->hasVideo === true ){
                $has_video_sql_filter = " and EXISTS (
                   select 1
                    from vlogFiles
                    where vlogFiles.callID = statCallStart.callId
                      and vlogFiles.mimetype = 'video'
                )\n";
            }
            else{
                $has_video_sql_filter = " and NOT EXISTS (
                   select 1
                    from vlogFiles
                    where vlogFiles.callID = statCallStart.callId
                      and vlogFiles.mimetype = 'video'
                )\n";
            }

            if($this->hasMessages === true ){
                $has_messages_sql_filter = " and EXISTS (
                    select 1
                    from msgMessages
                    where msgMessages.callID = statCallStart.callId
                      or msgMessages.savedCallID = statCallStart.callId
                )\n";
            }
            else{
                $has_messages_sql_filter = " and NOT EXISTS (
                    select 1
                    from msgMessages
                    where msgMessages.callID = statCallStart.callId
                      or msgMessages.savedCallID = statCallStart.callId
                )\n";
            }

            if($this->hasRecordings === true ){
                $has_recording_sql_filter = " and EXISTS (
                     select 1
                    from vlogFiles
                    where vlogFiles.callID = statCallStart.callId
                      and vlogFiles.mimetype <> 'video'
                )\n";
            }
            else{
                $has_recording_sql_filter = " and NOT EXISTS (
                     select 1
                    from vlogFiles
                    where vlogFiles.callID = statCallStart.callId
                      and vlogFiles.mimetype <> 'video'
                )\n";
            }
        }

        if(strlen($this->allowed_accounts)){
            $allowed_accounts_filter = '';
            $items = explode(",", implode(",", array_filter(explode("\n", trim($this->allowed_accounts)))));
            foreach($items as $item){
                if(Str::contains($item, '-')){
                    $parts = explode("-", $item);
                    $allowed_accounts_filter .= "or cltClients.ClientNumber between {$parts[0]} and {$parts[1]}\n";
                }
                else{
                    $allowed_accounts_filter .= "or cltClients.ClientNumber in ({$item})\n";
                }
            }

            if(Str::startsWith($allowed_accounts_filter, 'or' )){
                $allowed_accounts_filter = "and (" . substr($allowed_accounts_filter, 2) . ")\n";
            }
        }else{ $allowed_accounts_filter = '';}

        if(strlen($this->allowed_billing)){
            $allowed_billing_filter = '';
            $items = explode(",", implode(",", array_filter(explode("\n", trim($this->allowed_billing)))));
            foreach($items as $item){
                if(Str::contains($item, '-')){
                    $parts = explode("-", $item);
                    $allowed_billing_filter .= "or cltClients.BillingCode between {$parts[0]} and {$parts[1]}\n";
                }
                else{
                    $allowed_billing_filter .= "or cltClients.BillingCode in ({$item})\n";
                }
            }

            if(Str::startsWith($allowed_billing_filter, 'or' )){
                $allowed_billing_filter = "and (" . substr($allowed_billing_filter, 2) . ")\n";
            }

        }else{ $allowed_billing_filter = '';}

        $total_filter = "{$client_sql_filter} {$calltype_sql_filter} {$agent_sql_filter} {$ani_sql_filter} {$min_duration_sql_filter} {$max_duration_sql_filter} {$keyword_search_sql_filter} {$has_video_sql_filter} {$has_messages_sql_filter} {$has_recording_sql_filter}{$allowed_accounts_filter}{$allowed_billing_filter}";
        $total_filter = rtrim($total_filter);

        return trim(<<<TSQL
            select
                statCallStart.callId as [CallId]
                ,cltClients.ClientNumber as [ClientNumber]
                ,cltClients.ClientName as [ClientName]
                ,cltClients.BillingCode as [BillingCode]
                ,statCallStart.Stamp as [CallStart]
                ,statCallEnd.Stamp as [CallEnd]
                ,statCallStart.Source as [Channel]
                ,statCallStart.ANI as [CallerANI]
                ,statCallStart.CallerName as [CallerName]
                ,statCallStart.DNIS as [CallerDNIS]
                ,statCallStart.Diversion as [Diversion]
                ,statCallStart.DiversionReason as [DiversionReason]
                ,statCallEnd.[Kind]
                ,statCallEnd.[CompCode]
                ,statCallEnd.[TimezoneOffset]
                ,statCallEnd.[stationType]
                ,statCallEnd.[stationNumber]
                ,statCallEnd.[LastRoute]
                ,statCallEnd.[SkillId]
                ,statCallEnd.[SkillName]
                ,statCallEnd.[CallNote]
                ,statCallEnd.[agtId]
                ,(
                    statCallEnd.[selDisc] +
                    statCallEnd.[unselDisc] +
                    statCallEnd.[selRing] +
                    statCallEnd.[unselRing] +
                    statCallEnd.[selTalk] +
                    statCallEnd.[unselTalk] +
                    statCallEnd.[selTalk1] +
                    statCallEnd.[unselTalk1] +
                    statCallEnd.[selTalk2] +
                    statCallEnd.[unselTalk2] +
                    statCallEnd.[selConference] +
                    statCallEnd.[unselConference] +
                    statCallEnd.[selHold] +
                    statCallEnd.[unselHold] +
                    statCallEnd.[selInProgress] +
                    statCallEnd.[unselInProgress] +
                    statCallEnd.[selVoiceMail] +
                    statCallEnd.[unselVoiceMail] +
                    statCallEnd.[selAuto] +
                    statCallEnd.[unselAuto] +
                    statCallEnd.[selOutboundQueue] +
                    statCallEnd.[unselOutboundQueue] +
                    statCallEnd.[selAutoHold] +
                    statCallEnd.[unselAutoHold] +
                    statCallEnd.[selPatch] +
                    statCallEnd.[unselPatch] +
                    statCallEnd.[selBridge] +
                    statCallEnd.[unselBridge]
                ) as [CallDuration]
                ,agtAgents.Name as [AgentName]
                ,agtAgents.Initials as [AgentInitials]
                 ,(
                    SELECT STRING_AGG(cast(agtId as nvarchar(max)) + '-' + agtName + '-' + agtInitials + '-' + cast(stnNumber as nvarchar(max))+ '-' + cast(stnType as nvarchar(max)), ', ')
                        FROM (
                            SELECT DISTINCT agt.Name AS agtName,
                            agt.agtId as agtId,
                            agt.Initials as agtInitials,
                            sct.stationNumber as stnNumber,
                            sct.stationType as stnType
                            FROM statCallTracker sct
                            JOIN agtAgents agt ON sct.agtId = agt.agtId
                            WHERE sct.callId = statCallStart.callId
                        ) AS distinctAgents
                ) as [AgentList]
                ,CASE
                WHEN EXISTS (
                    select 1
                    from msgMessages
                    where msgMessages.callID = statCallStart.callId
                      or msgMessages.savedCallID = statCallStart.callId
                )
                THEN 1
                ELSE 0
            END as [hasMessages]
            ,CASE
                WHEN EXISTS (
                    select 1
                    from vlogFiles
                    where vlogFiles.callID = statCallStart.callId
                      and vlogFiles.mimetype <> 'video'
                )
                THEN 1
                ELSE 0
            END as [hasRecordings]
            ,CASE
                WHEN EXISTS (
                    select 1
                    from vlogFiles
                    where vlogFiles.callID = statCallStart.callId
                      and vlogFiles.mimetype = 'video'
                )
                THEN 1
                ELSE 0
            END as [hasVideo]
            from statCallEnd
            left join statCallStart on statCallStart.callId = statCallEnd.callId
            left join cltClients on statCallStart.cltID = cltClients.cltId
            left join agtAgents on statCallEnd.agtId = agtAgents.agtId
            where statCallEnd.[Stamp] between ? and ? {$total_filter}
            order by {$this->sortBy} {$this->sortDirection}
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
