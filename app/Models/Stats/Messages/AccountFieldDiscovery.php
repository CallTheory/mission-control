<?php

declare(strict_types=1);

namespace App\Models\Stats\Messages;

use App\Models\Stats\Helpers;
use App\Models\Stats\Stat;
use stdClass;

class AccountFieldDiscovery extends Stat
{
    public string $client_number;

    public function __construct(string $client_number)
    {
        $this->client_number = $client_number;

        parent::__construct();
    }

    public function validateParams(): bool
    {
        $this->parameters['client_number'] = $this->client_number;

        return true;
    }

    public function tsql(): string
    {
        return trim(<<<'TSQL'
            SELECT TOP 50
                msgMessages.[XmlMessage]
            FROM msgMessages
            INNER JOIN cltClients ON cltClients.cltId = msgMessages.cltId
            WHERE cltClients.ClientNumber = ?
            AND msgMessages.[XmlMessage] IS NOT NULL
            ORDER BY msgMessages.[Stamp] DESC
            TSQL);
    }

    /**
     * Parse sampled messages and return a deduplicated, sorted array of field names.
     */
    public function getAvailableFields(): array
    {
        $fieldNames = [];

        foreach ($this->results as $row) {
            if (empty($row->XmlMessage)) {
                continue;
            }

            try {
                $parsed = Helpers::parseXmlMessage($row->XmlMessage);

                if (! empty($parsed['fields'])) {
                    // Get the last revision's fields (most current state)
                    $lastRevisionFields = end($parsed['fields']);

                    if (is_array($lastRevisionFields)) {
                        foreach (array_keys($lastRevisionFields) as $fieldName) {
                            $fieldNames[$fieldName] = true;
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $fields = array_keys($fieldNames);
        sort($fields);

        return $fields;
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
