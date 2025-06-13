<?php

namespace App\Models\API;

use App\Models\DataSource;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

class RecentCaller
{
    protected int $ani;

    protected int $clientNumber;

    protected DataSource $datasource;

    protected array $parameters = [];

    protected int $results_to_return = 5;

    public function __construct(int $ani, int $clientNumber)
    {
        $this->ani = $ani;
        $this->clientNumber = $clientNumber;
        $this->datasource = DataSource::firstOrFail();
    }

    /**
     * @throws Exception
     */
    public function recent(?int $results_to_return = null): array
    {
        if ($results_to_return) {
            $this->results_to_return = $results_to_return;
        }

        return $this->getListOfRecentCallsFromNumber();
    }

    /**
     * @throws Exception
     */
    protected function getListOfRecentCallsFromNumber(): array
    {
        Config::set('database.connections.intelligent', [
            'driver' => 'sqlsrv',
            'host' => $this->datasource->is_db_host,
            'port' => $this->datasource->is_db_port,
            'database' => $this->datasource->is_db_data,
            'username' => $this->datasource->is_db_user,
            'password' => decrypt($this->datasource->is_db_pass),
            'encrypt' => true,
            'trust_server_certificate' => true,
        ]);

        $this->parameters = [
            'ANI' => $this->ani,
            'ClientNumber' => $this->clientNumber,
        ];

        try {
            $results = DB::connection('intelligent')->select($this->tsql(), array_values($this->parameters));
        } catch (Exception $e) {
            if (App::environment('local')) {
                throw $e;
            }
            throw new Exception('Unable to query call data.');
        }

        $fields_from_messages = [];
        foreach ($results as $i => $object) {
            foreach ($object as $key => $value) {
                if ($key === 'xmlMessage') {
                    $xmlTemp = new SimpleXMLElement($value);
                    $fields = $xmlTemp->xpath('//message/revision[last()]/fields');
                    $fields = json_decode(json_encode($fields), true);

                    $wanted = array_keys(request()->except(['ANI', 'ResultCount']));
                    // get rid of any empty arrays, and convert to string
                    // Intelligent Series does not handle this well and converts it to a literal string of `[]`
                    // if we have request params for particular fields, we clear those out

                    foreach ($fields as $arrayIterator => $fieldArray) {
                        foreach ($fieldArray as $fieldKey => $fieldValue) {
                            if ($fieldValue) {
                                $fields[$arrayIterator][$fieldKey] = trim(str_replace('*', '', $fieldValue));
                            }

                            if (in_array(strtoupper($fieldKey), $wanted)) {
                                if (is_array($fieldValue) && count($fieldValue) === 0) {
                                    $fields[$arrayIterator][$fieldKey] = '';
                                }
                            } else {
                                if (count($wanted) > 0) {
                                    // We want to remove unwanted fields to de-dupe
                                    unset($fields[$arrayIterator][$fieldKey]);
                                }
                                // return all fields for every record
                            }
                        }
                    }

                    $fields_from_messages[hash('SHA256', json_encode($fields[0]))] = $fields[0];
                }
            }
        }

        return array_filter($fields_from_messages);
    }

    protected function tsql(): string
    {

        return trim(<<<TSQL
select top {$this->results_to_return}
    calls.callId,
    calls.Stamp,
    calls.ANI,
    msgs.[xmlMessage],
    clients.ClientName,
    clients.ClientNumber
from statCallStart calls
    left join cltclients clients on clients.cltid = calls.cltid
    left join msgmessages msgs on msgs.savedCallId = calls.callid
where
    msgs.xmlmessage is not null
    and calls.ANI = ?
    and clients.ClientNumber = ?
order by calls.stamp desc;
TSQL);
    }
}
