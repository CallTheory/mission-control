<?php

namespace App\Models\Stats\Aggregate;

use App\Models\Stats\Stat;
use Illuminate\Support\Str;

class TalkTime extends Stat
{
    private string $allowed_accounts;
    private string $allowed_billing;

    public function __construct(array $parameters)
    {
        $this->allowed_accounts = $parameters['allowed_accounts'];
        $this->allowed_billing = $parameters['allowed_billing'];
        parent::__construct($parameters);
    }

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

        return trim(<<<TSQL
            select avg(statCallEnd.selTalk + statCallEnd.selTalk1 + statCallEnd.selTalk2) as [average] from statCallEnd
            left join cltClients on cltClients.cltId = statCallEnd.cltId
            where statCallEnd.Kind in (1,2) and statCallEnd.Stamp between ? and ?
            {$allowed_accounts_filter}{$allowed_billing_filter}
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
