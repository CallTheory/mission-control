<?php

namespace App\Jobs;

use App\Models\DataSource;
use App\Models\MergeCommISWebTrigger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class SyncMergeCommWebHooks implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $datasources = DataSource::firstOrFail();

        Config::set('database.connections.intelligent', [
            'driver' => 'sqlsrv',
            'host' => $datasources->is_db_host,
            'port' => $datasources->is_db_port,
            'database' => $datasources->is_db_data,
            'username' => $datasources->is_db_user,
            'password' => decrypt($datasources->is_db_pass),
            'encrypt' => true,
            'trust_server_certificate' => true,
        ]);

        $web_api_triggers = DB::connection('intelligent')
            ->select('select ci.inboundID, c.clientNumber, c.cltId, ci.Description, ci.LookupId from cltInbound ci left join cltclients c on c.cltid = ci.cltid where ci.Type = 9');

        foreach ($web_api_triggers as $trigger) {
            if (
                $trigger->clientNumber !== null &&
                $trigger->inboundID !== null &&
                $trigger->cltId !== null &&
                $trigger->Description !== null &&
                $trigger->LookupId !== null &&
                $datasources->is_agent_username !== null &&
                $datasources->is_agent_password !== null
            ) {
                $add = MergeCommISWebTrigger::firstOrNew(['inboundID' => $trigger->inboundID]);

                $add->clientNumber = $trigger->clientNumber;
                $add->inboundID = $trigger->inboundID;
                $add->clientId = $trigger->cltId;
                $add->message = $trigger->Description;
                $add->apiKey = $trigger->LookupId;
                $add->login = $datasources->is_agent_username;
                $add->password = $datasources->is_agent_password;
                $add->save();
            }
        }
    }
}
