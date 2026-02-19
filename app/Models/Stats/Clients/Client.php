<?php

namespace App\Models\Stats\Clients;

use App\Models\Stats\Stat;

/**
 * @property string|null $ClientNumber
 * @property int|null $cltId
 * @property string|null $ClientName
 * @property string|null $BillingCode
 */
class Client extends Stat
{
    public function validateParams(): bool
    {
        return true;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            select
                cltClients.cltId,
                cltclients.Inactive,
                cltClients.ClientName,
                cltClients.ClientNumber,
                cltClients.BillingCode,
                cltClients.Stamp,
                cltClients.DIDLimit,
                cltClients.MsgPurgeTime,
                cltClients.TimezoneOffset,
                cltClients.LogVoice,
                cltClients.PerfectAnswer,
                cltClients.AutoConnect,
                cltClients.[Emergency],
                cltClients.DoneKeyCancelsScript,
                cltClients.HangupRemovesWorkArea,
                cltClients.TransferConfRemovesWorkArea,
                cltClients.PciCompliance,
                cltClients.ReassignTime,
                cltClients.DefaultRoute,
                cltClients.MusicOnHoldAfterAutoAnswer,
                cltClients.AutoAnswerRings,
                cltClients.AnnounceATTA,
                cltClients.RepeatATTA,
                cltClients.AnnounceCallsInQue,
                cltClients.CallerIdNumber,
                cltClients.CallerIdName,
                cltClients.Skill,
                acdSkills.[Name] as [SkillName],
                cltClients.ScreenCapture,
                cltClients.SelectNextUndelMsgWhenDel,
                cltClients.PlayQualityPrompt,
                cltClients.LoggerBeep,
                cltClients.LoggerBeepInterval,
                cltClients.AutoAnswerInterval,
                cltClients.SaveEditedSpecial,
                cltClients.ShowSpecials,
                cltClients.ShowInfos,
                cltClients.SpecialOldToNew,
                cltClients.AnswerPhrase,
                dirSubjects.[Name] as [DirectorySubject],
                dirViews.[Name] as [DirectoryView],
                dirOnCallSchedules.[Name] as [DirectorySchedule],
                msgSCripts.[Name] as [ScriptName],
                msAcd.[Name] as [AcdScriptName],
                msWeb.[Name] as [WebScriptName],
                msWebM.[Name] as [WebMessagingScriptName],
                msMerge.[Name] as [MergeCommScriptName]
             from cltClients
            left join dirSubjects on dirSubjects.subId = cltClients.subId
            left join msgScripts on msgScripts.scriptId = cltClients.msgScriptId
            left join msgScripts msAcd on msAcd.scriptId = cltClients.msgScriptIdAcd
            left join msgScripts msWeb on msWeb.scriptId = cltClients.msgScriptIDWeb
            left join msgScripts msWebM on msWebM.scriptId = cltClients.msgScriptIDWebMessaging
            left join msgScripts msMerge on msMerge.scriptId = cltClients.msgScriptIDMergeComm
            left join dirViews on dirViews.viewId = cltClients.viewId
            left join dirOnCallSchedules on dirOnCallSchedules.ocschedID = cltClients.ocschedID
            left join acdSkills on acdSkills.skillID = cltClients.Skill
             where cltClients.clientNumber = ?
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
