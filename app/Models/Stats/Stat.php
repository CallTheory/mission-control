<?php

namespace App\Models\Stats;

use App\Models\DataSource;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use stdClass;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

abstract class Stat
{
    public array $results;

    public DataSource $datasource;

    public array $parameters;

    /**
     * @throws Exception
     */
    public function __construct(array $params = [])
    {
        $this->datasource = DataSource::firstOrFail();
        $this->parameters = $params;

        if (! $this->validateParams()) {
            throw new BadRequestException('Parameter validation was not successful');
        }

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

        try {
            $this->results = DB::connection('intelligent')->select($this->tsql(), array_values($this->parameters));
        } catch (Exception $e) {
            if (App::environment('local')) {
                throw $e;
            }

            throw new Exception('Unable to query call data.');
        }
    }

    public function details(): stdClass|array|null
    {
        if (isset($this->results[0])) {
            return $this->results[0];
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public function __set($key, $value)
    {
        throw new Exception('Setting a value is not supported.');
    }

    /**
     * @throws Exception
     */
    public function __unset($key)
    {
        throw new Exception('Unsetting a value is not supported.');
    }

    abstract public function __get($key);

    abstract public function __isset($key);

    // todo: abstract function storedProcedure(): string;
    abstract public function tsql(): string;

    abstract public function validateParams(): bool;
}
