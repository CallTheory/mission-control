<?php

namespace App\Livewire\System;

use Exception;
use App\Models\DataSource;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Component;
use PDO;
use Illuminate\Support\Facades\App;

class ScriptSearch extends Component
{
    public mixed $searchResults = null;
    public string $searchStatus = 'loading';

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        try{
            $datasource = DataSource::first();
            Config::set('database.connections.intelligent', [
                'driver' => 'sqlsrv',
                'host' => $datasource->is_db_host,
                'port' => $datasource->is_db_port,
                'database' => $datasource->is_db_data,
                'username' => $datasource->is_db_user,
                'password' => decrypt($datasource->is_db_pass),
                'encrypt' => true,
                'trust_server_certificate' => true,
                'options' => [
                    PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 300
                ]
            ]);
        }
        catch(Exception $e){}

        $this->updateBrokenScriptList();
    }

    /**
     * @throws Exception
     */
    public function updateBrokenScriptList(): void
    {
        $this->searchStatus = 'loading';
        try {
            $this->searchResults = DB::connection('intelligent')->select($this->tsql());
            $this->searchStatus = 'success';
        } catch (Exception $e) {
            if(App::environment('local')){
                throw $e;
            }
            Log::error($e->getMessage());
            $this->searchResults = null;
            $this->searchStatus = 'database-error';
        }
    }

    public function render(): View
    {
        return view('livewire.system.script-search');
    }

    private function tsql(): string
    {
        return "select
    p.pageid as PageID,
	c.ClientName as ClientName,
		c.ClientNumber as ClientNumber,
		s.[Name] as ScriptName,
		p.[Name] as PageName,
		s.[IsSystemScript] as SystemScript
from msgScriptVersionPages p
left join msgScriptVersions v on v.scriptversionId = p.scriptversionID
left join msgscripts s on s.scriptid = v.scriptid
left join cltclients c on c.cltid = s.cltid
where
cast(p.script as varbinary(3)) <> cast(0x1F8B08 as varbinary(3))
and p.scriptversionID = s.activescriptversionId
order by c.ClientNumber asc;
";
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 py-4 text-sm">
           Loading broken scripts...this might take a few minutes.
        </div>
        HTML;
    }

}
