<?php

namespace App\Models\Stats\Agents;

use App\Models\Stats\Stat;
use stdClass;
use Exception;

class Agent extends Stat
{
    /**
     * @throws Exception
     */
    public function skillGroups(): stdClass|array|null
    {
        $result = new SkillGroups($this->parameters);

        return $result->details();
    }

    public function validateParams(): bool
    {
        if (
            array_key_exists('agent_name', $this->parameters) //agent_name must be included
            && strlen($this->parameters['agent_name']) > 0 //the length of agent_name must be greater than 0
        ) {
            return true;
        } elseif (
            array_key_exists('agtId', $this->parameters) //agtId must be included
            && strlen($this->parameters['agtId']) > 0 //the length of agent_name must be greater than 0
        ) {
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        if (isset($this->parameters['agtId'])) {
            return trim(<<<'TSQL'
                select agtId, agtAgents.Name, Initials, agtAgents.Stamp, agtType, DispatchGroups, agtagents.subID, dirSubjects.Name as [DirectorySubject],dirViews.Name as [ViewName], cltClients.ClientNumber , agtAgents.viewID,
                       LoginFailures, LockedOut, VoiceLogger, SwitchLogin, SwitchPassword, agtAgents.AutoConnect,
                       NewChatToForeground, FlashNewChat, ExcludeFromChat, CallLimit, TakeCallInWaits, DockedChat,
                       CallLogAccess, CallLogAdvancedSearch, MiTeamWebLayoutEdit, MiTeamWebAdmin, ScreenCaptureAccess,
                       DeliverResumesDispatch, AutoDisplaySideBar, DefaultDispatchViewAll, cltIdDefault, CallLogClientNumber,
                       CallLogBillingCode, MonitorLogSettings, SystemScheduleFilterLayouts, EzWaitsLayout, NotifyWhenNotReady,
                       MaximizeAgentOnLogin, DirectoryModifierLayouts, UseLogoutReasons, UseNotReadyReasons, AcdAgentOptions,
                       AllowToggleCallRecording, FilterMonitorBySkillGroup, agtAgents.StyleId, sysAgentStyles.Name as [StyleName], DisableSpy
                from agtAgents
                left join sysAgentStyles on sysAgentStyles.styleId = agtagents.StyleId
                left join dirSubjects on dirSubjects.subid = agtAgents.subId
                left join dirViews on dirViews.viewId = agtAgents.viewId
                left join cltClients on cltClients.cltId = agtAgents.cltIdDefault
                where agtAgents.agtId = ?;
            TSQL);
        } else {
            return trim(<<<'TSQL'
                select agtId, agtAgents.Name, Initials, agtAgents.Stamp, agtType, DispatchGroups, agtagents.subID, dirSubjects.Name as [DirectorySubject],dirViews.Name as [ViewName], cltClients.ClientNumber , agtAgents.viewID,
                       LoginFailures, LockedOut, VoiceLogger, SwitchLogin, SwitchPassword, agtAgents.AutoConnect,
                       NewChatToForeground, FlashNewChat, ExcludeFromChat, CallLimit, TakeCallInWaits, DockedChat,
                       CallLogAccess, CallLogAdvancedSearch, MiTeamWebLayoutEdit, MiTeamWebAdmin, ScreenCaptureAccess,
                       DeliverResumesDispatch, AutoDisplaySideBar, DefaultDispatchViewAll, cltIdDefault, CallLogClientNumber,
                       CallLogBillingCode, MonitorLogSettings, SystemScheduleFilterLayouts, EzWaitsLayout, NotifyWhenNotReady,
                       MaximizeAgentOnLogin, DirectoryModifierLayouts, UseLogoutReasons, UseNotReadyReasons, AcdAgentOptions,
                       AllowToggleCallRecording, FilterMonitorBySkillGroup, agtAgents.StyleId, sysAgentStyles.Name as [StyleName], DisableSpy
                from agtAgents
                left join sysAgentStyles on sysAgentStyles.styleId = agtagents.StyleId
                left join dirSubjects on dirSubjects.subid = agtAgents.subId
                left join dirViews on dirViews.viewId = agtAgents.viewId
                left join cltClients on cltClients.cltId = agtAgents.cltIdDefault
                where agtAgents.Name = ?;
            TSQL);
        }
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
