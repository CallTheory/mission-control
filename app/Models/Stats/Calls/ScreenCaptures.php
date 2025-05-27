<?php

namespace App\Models\Stats\Calls;

use StdClass;

class ScreenCaptures extends Call
{

    public function __construct($parameters)
    {
        $this->parameters = $parameters;
        parent::__construct($this->parameters);
    }
    public function details(): stdClass|array|null
    {
        return $this->results ?? null;
    }

    public function tsql(): string
    {
        if(isset($this->parameters['ScreenCaptureData']) && $this->parameters['ScreenCaptureData'] === true){
            $query = ',vlogStreams.[Data]';
        }else{
            $query = '--,vlogStreams.[Data]';
        }

        return trim(<<<TSQL
            select
            vlogFiles.fileID
            ,vlogFiles.callID
            ,vlogFiles.agtID
            ,vlogFiles.msgID
            ,vlogFiles.stnType
            ,vlogFiles.stnNumber
            ,vlogFiles.extCallID
            ,vlogFiles.fileName
            ,vlogFiles.Stamp
            ,vlogFiles.Archived
            ,vlogFiles.MimeType
            ,vlogFiles.CallState
            ,vlogFiles.format
            {$query}
            from vlogFiles
            left join vlogStreams on vlogStreams.fileID = vlogFiles.fileID
            where vlogFiles.callid = ?
            and vlogFiles.MimeType = 'video'
            order by vlogFiles.[stamp] asc
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
