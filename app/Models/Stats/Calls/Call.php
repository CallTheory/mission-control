<?php

namespace App\Models\Stats\Calls;

use App\Models\Stats\Stat;
use Exception;
use stdClass;

class Call extends Stat
{
    /**
     * @throws Exception
     */
    public function messages(): stdClass|array|null
    {
        $result = new Messages($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function history(): stdClass|array|null
    {
        $result = new History($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function tracker(): stdClass|array|null
    {
        $result = new Tracker($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function statistics(): stdClass|array|null
    {
        $result = new Statistics($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function recordings(): stdClass|array|null
    {
        $result = new Recordings($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function recordingData(): stdClass|array|null
    {
        $result = new RecordingData($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function screenCapture(): stdClass|array|null
    {
        $result = new ScreenCaptures($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function agents(): stdClass|array|null
    {
        $result = new Agents($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function clients(): stdClass|array|null
    {
        $result = new Clients($this->parameters);

        return $result->details();
    }

    /**
     * @throws Exception
     */
    public function transcription(): stdClass|array|null
    {
        $result = new Transcriptions($this->parameters);

        return $result->details();
    }

    public function validateParams(): bool
    {
        if (
            array_key_exists('ISCallId', $this->parameters) // ISCallId must be included
            && is_int((int) $this->parameters['ISCallId'])  // ISCallId must be an integer value
            && (int) $this->parameters['ISCallId'] > 0) { // ISCallId must be greater than 0
            return true;
        }

        return false;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
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
            from statCallStart
            left join statCallEnd on statCallStart.callId = statCallEnd.callId
            left join cltClients on statCallStart.cltID = cltClients.cltId
            where statCallStart.callId = ? order by statCallStart.Stamp asc
            TSQL);
    }

    /**
     * @throws Exception
     */
    public function __get($key)
    {
        switch ($key) {
            case 'messages':
                return $this->messages();
            case 'history':
                return $this->history();
            case 'tracker':
                return $this->tracker();
            case 'details':
                return $this->details();
            case 'recordings':
                return $this->recordings();
            case 'clients':
                return $this->clients();
            case 'statistics':
                return $this->statistics();
            case 'agents':
                return $this->agents();
            case 'transcription':
                return $this->transcription();
            default:
                break;
        }

        if (isset($this->results[0])) {
            if (isset($this->results[0]->{$key})) {
                return $this->results[0]->{$key};
            }
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function __isset($key)
    {
        switch ($key) {
            case 'messages':
                return is_null($this->messages());
            case 'history':
                return is_null($this->history());
            case 'tracker':
                return is_null($this->tracker());
            case 'details':
                return is_null($this->details());
            case 'recordings':
                return is_null($this->recordings());
            case 'screencapture':
                return is_null($this->screenCapture());
            case 'clients':
                return is_null($this->clients());
            case 'statistics':
                return is_null($this->statistics());
            case 'agents':
                return is_null($this->agents());
            case 'transcription':
                return is_null($this->transcription());
            default:
                break;
        }

        if (isset($this->results[0])) {
            return isset($this->results[0]->{$key});
        }

        return false;
    }
}
