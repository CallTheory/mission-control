<?php

namespace App\Livewire\Utilities;

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
    public string $searchQuery = '';

    /**
     * @throws Exception
     */
    public function searchScriptElements(): void
    {
        $this->validate(['searchQuery' => 'required|string|min:3|max:255']);

        $datasource = DataSource::firstOrFail();
        Config::set('database.connections.intelligent', [
            'driver' => 'sqlsrv',
            'host' => $datasource->is_db_host,
            'port' => $datasource->is_db_port,
            'database' => $datasource->is_db_data,
            'username' => $datasource->is_db_user,
            'password' => decrypt($datasource->is_db_pass),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'encrypt' => true,
            'trust_server_certificate' => true,
            'options' => [
                PDO::SQLSRV_ATTR_QUERY_TIMEOUT => 300
            ]
        ]);

        try {
            $params = ['searchQuery' => $this->searchQuery];
            $this->searchResults = DB::connection('intelligent')->select($this->tsql(), $params);

        } catch (Exception $e) {
            if(App::environment('local')){
                throw $e;
            }
            Log::error($e->getMessage());
            $this->searchResults = null;
        }

        $this->dispatch('search');
    }

    public function render(): View
    {
        return view('livewire.utilities.script-search');
    }

    private function tsql():string
    {
        return "select p.pageid as PageID,
c.ClientName as ClientName,
c.ClientNumber as ClientNumber,
s.[Name] as ScriptName,
p.[Name] as PageName,
s.[IsSystemScript] as SystemScript
from msgScriptVersionPages p
left join msgScriptVersions v on v.scriptversionId = p.scriptversionID
left join msgscripts s on s.scriptid = v.scriptid
left join cltclients c on c.cltid = s.cltid
where cast(p.script as varbinary(3)) = cast(0x1F8B08 as varbinary(3))
and p.scriptversionID = s.activescriptversionId
and CHARINDEX(:searchQuery, CAST(DECOMPRESS(CAST(p.script AS varbinary(max))) AS nvarchar(max))) > 0
";
    }
}
