<?php

namespace App\Models\Stats\Calls;

use Illuminate\Support\Facades\Redis;
use StdClass;

class Transcriptions extends Call
{

    public array $parameters;

    public function __construct($parameters)
    {
        $this->parameters = $parameters;
        parent::__construct();
    }

    public function details(): stdClass|array|null
    {
        $redis_key = "{$this->parameters['ISCallId']}.json";
        $transcription = json_decode(Redis::get($redis_key), true);

        $formatted_divs = '';
        if($transcription && isset($transcription['transcription'])){
            foreach($transcription['transcription'] as $transcription_line) {
                $formatted_divs .= "<span class=\"text-sm\" data-offsetFrom=\"{$transcription_line['offsets']['from']}\" data-offsetTo=\"{$transcription_line['offsets']['to']}\">{$transcription_line['text']}</span>";
            }
        }

        if(strlen($formatted_divs)){
            return [
                'html' => $formatted_divs ?? '',
            ];
        }
        else{
            return null;
        }

    }
}
