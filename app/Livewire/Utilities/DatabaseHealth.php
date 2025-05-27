<?php

namespace App\Livewire\Utilities;

use App\Models\DataSource;
use App\Models\Stats\Helpers;
use App\Models\Stats\Schedule\Maintenance;
use App\Models\System\Settings;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\App;
use Livewire\WithPagination;

class DatabaseHealth extends Component
{
    use WithPagination;

    #[Url]
    public int $page;

    public $results = null;

    public $maintenance_schedule, $maintenance_checklist;
    private DataSource $datasource;

    public string $switch_timezone;

    public array $scheduleTypes, $scheduleRecurrenceTypes;
    public function placeholder(): string
    {
        return <<<'HTML'
        <div class="mx-2 text-sm my-4 px-4 py-2 bg-white w-full">
           Loading database details...one moment, please.
        </div>
        HTML;
    }

    /**
     * @throws Exception
     */
    public function mount(): void
    {
        $settings = Settings::first();
        $this->switch_timezone = $settings->switch_data_timezone ?? 'UTC';
        $this->scheduleTypes = Helpers::scheduleTypes();
        $this->scheduleRecurrenceTypes = Helpers::scheduleRecurrenceTypes();
        $this->datasource = DataSource::firstOrFail();
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
            $this->results = DB::connection('intelligent')->selectResultSets($this->tsql());
        } catch (Exception $e) {
            if(App::environment('local')){
                throw $e;
            }
            Log::error($e->getMessage());
            $this->results = null;
        }

        $maintenance = new Maintenance();
        $this->maintenance_schedule = $maintenance->details();
        $checklist = collect($this->maintenance_schedule);

        $this->maintenance_checklist = [
            'archive' => $checklist->contains(function($val, int $key){
                if($val->Action == 0){ //Archive
                    return true;
                }
                return false;
            }),
            'purge' => $checklist->contains(function($val, int $key){
                if($val->Action == 1){ //Purge
                    return true;
                }
                return false;
            }),
            'archive_oncall' => $checklist->contains(function($val, int $key){
                if($val->Action == 11){ //ArchiveOncall
                    return true;
                }
                return false;
            }),
            'purge_oncall' => $checklist->contains(function($val, int $key){
                if($val->Action == 12){ //PurgeOncall
                    return true;
                }
                return false;
            }),
            'archive_voicelogs' => $checklist->contains(function($val, int $key){
                if($val->Action == 9){ //ArchiveVoiceLogs
                    return true;
                }
                return false;
            }),
            'purge_voicelogs' => $checklist->contains(function($val, int $key){
                if($val->Action == 10){ //PurgevoiceLogs
                    return true;
                }
                return false;
            }),
            'purge_stats' => $checklist->contains(function($val, int $key){
                if($val->Action == 8){ //PurgeStats
                    return true;
                }
                return false;
            }),
        ];
    }

    public function render(): View
    {
        return view('livewire.utilities.database-health');
    }

    private function tsql():string
    {
        return "declare @DatabaseServerInformation nvarchar(max);
declare @Hostname nvarchar(50) = (select convert(varchar(50),@@SERVERNAME));
declare @Version nvarchar(max) = (select convert(varchar(max),@@version));
declare @Edition nvarchar(50) = (select convert(varchar(50),SERVERPROPERTY('edition')));
declare @IsClusteredInstance nvarchar(50) = (SELECT CASE SERVERPROPERTY ('IsClustered') WHEN 1 THEN 'Clustered Instance' WHEN 0 THEN 'Non Clustered instance' ELSE 'null' END);
declare @IsInstanceinSingleUserMode nvarchar(50) = (SELECT CASE SERVERPROPERTY ('IsSingleUser') WHEN 1 THEN 'Single user' WHEN 0 THEN 'Multi user' ELSE 'null' END);
select @Hostname as 'HostName', @Version as 'Version', @Edition as 'Edition', @IsClusteredInstance as 'Clustered', @IsInstanceinSingleUserMode as 'UserMode';

SELECT DISTINCT volumes.logical_volume_name AS LogicalName,
    volumes.volume_mount_point AS Drive,
    CONVERT(FLOAT,volumes.available_bytes/1024.00/1024.00/1024.00) AS FreeSpace,
    CONVERT(FLOAT,volumes.total_bytes/1024.00/1024.00/1024.00) AS TotalSpace,
    CONVERT(FLOAT,volumes.total_bytes/1024.00/1024.00/1024.00) - CONVERT(FLOAT,volumes.available_bytes/1024.00/1024.00/1024.00) AS OccupiedSpace
FROM sys.master_files mf
CROSS APPLY sys.dm_os_volume_stats(mf.database_id, mf.FILE_ID) volumes;

select a.database_id as database_id,
a.name as database_name,
a.create_date as created_date,
b.name as owner,
a.user_access_desc,
a.state_desc,
compatibility_level,
recovery_model_desc,
Sum((c.size*8.00)/1024.00) as DBSizeInMB
from sys.databases a inner join sys.server_principals b on a.owner_sid=b.sid inner join sys.master_files c on a.database_id=c.database_id
Where a.database_id>5
Group by a.name,a.create_date,b.name,a.user_access_desc,compatibility_level,a.state_desc, recovery_model_desc,a.database_id


SELECT
    DB.name AS DatabaseName,
    CASE
        WHEN BU.backup_finish_date IS NULL THEN 'No backup'
        ELSE 'Last backup finished on ' + CONVERT(VARCHAR(20), BU.backup_finish_date, 120)
    END AS BackupStatus,
    type as 'BackupType'
FROM sys.databases AS DB
LEFT JOIN (
    SELECT
        database_name,
        MAX(backup_finish_date) AS backup_finish_date,
        type as 'Type'
    FROM msdb.dbo.backupset
    GROUP BY database_name, type
) AS BU ON DB.name = BU.database_name
ORDER BY DB.name;


;WITH TableSizes AS (
    SELECT
        t.NAME AS TableName,
        p.rows AS NumberOfRows,
        SUM(a.total_pages) * 8 AS ReservedKB,
        SUM(a.used_pages) * 8 AS DataSizeKB,
        SUM(a.used_pages - a.data_pages) * 8 AS IndexSizeKB,
        SUM(a.total_pages - a.used_pages) * 8 AS UnusedKB
    FROM
        sys.tables AS t
    INNER JOIN
        sys.indexes AS i ON t.OBJECT_ID = i.object_id
    INNER JOIN
        sys.partitions AS p ON i.object_id = p.object_id AND i.index_id = p.index_id
    INNER JOIN
        sys.allocation_units AS a ON p.partition_id = a.container_id
    WHERE
        i.index_id <= 1  -- Clustered index or heap
    GROUP BY
        t.NAME, p.rows
)
, TotalSize AS (
    SELECT SUM(ReservedKB) AS TotalReservedKB
    FROM TableSizes
)
SELECT
    ts.TableName,
    ts.NumberOfRows,
    ts.ReservedKB,
    ts.DataSizeKB,
    ts.IndexSizeKB,
    ts.UnusedKB,
    CAST(ts.ReservedKB * 100.0 / t.TotalReservedKB AS DECIMAL(5, 2)) AS ReservedPercentage
FROM
    TableSizes ts, TotalSize t
ORDER BY
    ts.ReservedKB DESC;


";
    }
}
