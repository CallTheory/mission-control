<?php

declare(strict_types=1);

namespace App\Models\Stats\Messages;

use App\Models\Stats\Stat;
use Illuminate\Support\Str;
use stdClass;

class MessagesByAccount extends Stat
{
    public string $client_number;

    public string $start_date;

    public string $end_date;

    private string $allowed_accounts;

    private string $allowed_billing;

    public function __construct(
        string $client_number,
        string $start_date,
        string $end_date,
        string $allowed_accounts = '',
        string $allowed_billing = '',
    ) {
        $this->client_number = $client_number;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->allowed_accounts = $allowed_accounts;
        $this->allowed_billing = $allowed_billing;

        parent::__construct();
    }

    public function validateParams(): bool
    {
        $this->parameters['client_number'] = $this->client_number;
        $this->parameters['start_date'] = $this->start_date;
        $this->parameters['end_date'] = $this->end_date;

        return true;
    }

    public function tsql(): string
    {
        if (strlen($this->allowed_accounts)) {
            $allowed_accounts_filter = '';
            $items = explode(',', implode(',', array_filter(explode("\n", trim($this->allowed_accounts)))));
            foreach ($items as $item) {
                if (Str::contains($item, '-')) {
                    $parts = explode('-', $item);
                    $allowed_accounts_filter .= "or cltClients.ClientNumber between {$parts[0]} and {$parts[1]}\n";
                } else {
                    $allowed_accounts_filter .= "or cltClients.ClientNumber in ({$item})\n";
                }
            }

            if (Str::startsWith($allowed_accounts_filter, 'or')) {
                $allowed_accounts_filter = 'and (' . substr($allowed_accounts_filter, 2) . ")\n";
            }
        } else {
            $allowed_accounts_filter = '';
        }

        if (strlen($this->allowed_billing)) {
            $allowed_billing_filter = '';
            $items = explode(',', implode(',', array_filter(explode("\n", trim($this->allowed_billing)))));
            foreach ($items as $item) {
                if (Str::contains($item, '-')) {
                    $parts = explode('-', $item);
                    $allowed_billing_filter .= "or cltClients.BillingCode between {$parts[0]} and {$parts[1]}\n";
                } else {
                    $allowed_billing_filter .= "or cltClients.BillingCode in ({$item})\n";
                }
            }

            if (Str::startsWith($allowed_billing_filter, 'or')) {
                $allowed_billing_filter = 'and (' . substr($allowed_billing_filter, 2) . ")\n";
            }
        } else {
            $allowed_billing_filter = '';
        }

        return trim(<<<TSQL
            select
                msgMessages.[msgId]
                ,msgMessages.[XmlMessage]
                ,msgMessages.[Summary]
                ,msgMessages.[Stamp]
                ,msgMessages.[callid]
                ,msgMessages.[savedCallId]
                ,cltClients.[ClientNumber]
                ,cltClients.[ClientName]
                ,cltClients.[BillingCode]
                ,agtAgents.[Name] as [AgentName]
                ,agtAgents.[Initials] as [AgentInitials]
            from msgMessages
            left join cltClients on cltClients.cltId = msgMessages.cltId
            left join statMesgTaken on statMesgTaken.msgId = msgMessages.msgId
            left join agtAgents on agtAgents.agtId = statMesgTaken.agtId
            where cltClients.ClientNumber = ?
            and msgMessages.[Stamp] between ? and ?
            {$allowed_accounts_filter}{$allowed_billing_filter}
            order by msgMessages.[Stamp] desc
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
