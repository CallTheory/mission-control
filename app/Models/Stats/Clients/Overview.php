<?php

namespace App\Models\Stats\Clients;

use App\Models\Stats\Stat;
use Illuminate\Support\Str;

class Overview extends Stat
{
    private string $order_by;

    private string $order_direction;

    private $allowed_accounts;

    private $allowed_billing;

    private $client_number;

    private $client_name;

    private $billing_code;

    private $client_source;

    private string $account_setting;

    private string $account_setting_value;

    public function __construct(array $config)
    {
        $this->client_name = $config['client_name'] ?? '';
        $this->client_number = $config['client_number'] ?? '';
        $this->billing_code = $config['billing_code'] ?? '';
        $this->allowed_billing = $config['allowed_billing'] ?? '';
        $this->allowed_accounts = $config['allowed_accounts'] ?? '';
        $this->order_by = $config['order_by'] ?? 'ClientNumber';
        $this->order_direction = $config['order_direction'] ?? 'asc';
        $this->account_setting = $config['account_setting'] ?? '';
        $this->account_setting_value = $config['account_setting_value'] ?? '';
        $this->client_source = $config['client_source'] ?? '';

        // stupid version of validation
        if (strlen($this->account_setting_value)) {
            if ($this->account_setting_value == 0) {
                $this->account_setting_value = 0;
            } else {
                $this->account_setting_value = 1;
            }
        }
        parent::__construct();
    }

    public function validateParams(): bool
    {
        return true;
    }

    public function tsql(): string
    {
        if (strlen($this->client_name)) {
            $client_name_filter = "and cltClients.ClientName like concat('%', ?, '%')\n";
            $this->parameters['client_name_filter'] = $this->client_name;
        } else {
            $client_name_filter = '';
        }

        if (strlen($this->client_number)) {
            $client_number_filter = "and cltClients.ClientNumber like concat('%', ?, '%')\n";
            $this->parameters['client_number_filter'] = $this->client_number;
        } else {
            $client_number_filter = '';
        }

        if (strlen($this->billing_code)) {
            $billing_code_filter = "and cltClients.BillingCode like concat('%', ?, '%')\n";
            $this->parameters['billing_code_filter'] = $this->billing_code;
        } else {
            $billing_code_filter = '';
        }

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
                $allowed_accounts_filter = 'and ('.substr($allowed_accounts_filter, 2).")\n";
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
                $allowed_billing_filter = 'and ('.substr($allowed_billing_filter, 2).")\n";
            }

        } else {
            $allowed_billing_filter = '';
        }

        if (strlen($this->account_setting)) {
            $account_setting_filter = "and cltClients.{$this->account_setting} = ?\n";
            $this->parameters['account_setting'] = $this->account_setting_value;
        } else {
            $account_setting_filter = '';
        }

        if (strlen($this->client_source)) {
            $client_source_filter = "and exists ( select cltSources.Source from cltSources
                where cltClients.cltId = cltSources.cltid
                and cltSources.Source like concat('%', ?, '%')
            )\n";

            $this->parameters['client_source'] = $this->client_source;
        } else {
            $client_source_filter = '';
        }

        $full_filter = trim("{$client_name_filter}{$client_number_filter}{$billing_code_filter}{$allowed_accounts_filter}{$allowed_billing_filter}{$account_setting_filter}{$client_source_filter}");
        if (Str::startsWith($full_filter, 'and')) {
            $full_filter = 'where '.substr($full_filter, 3);
        }

        return <<<TSQL
                select
                cltClients.cltId, cltClients.Stamp, cltClients.ClientNumber, cltClients.ClientName,
                cltClients.BillingCode, subjects.[Name] as Directory
                from cltClients
                left join dirSubjects subjects on subjects.subid = cltclients.subid
                {$full_filter}
                order by {$this->order_by} {$this->order_direction}
            TSQL;
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
