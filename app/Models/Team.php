<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * @property bool $personal_team
 * @property bool|null $utility_api_gateway
 * @property bool|null $utility_better_emails
 * @property bool|null $utility_board_check
 * @property bool|null $utility_call_lookup
 * @property bool|null $utility_card_processing
 * @property bool|null $utility_cloud_faxing
 * @property bool|null $utility_config_editor
 * @property bool|null $utility_csv_export
 * @property bool|null $utility_database_health
 * @property bool|null $utility_directory_search
 * @property bool|null $utility_inbound_email
 * @property bool|null $utility_mcp_server
 * @property bool|null $utility_voicemail_digest
 * @property bool|null $utility_script_search
 * @property bool|null $utility_wctp_gateway
 * @property string|null $better_emails_config
 * @property string|null $recording_prefix
 * @property string|null $allowed_accounts
 * @property string|null $allowed_billing
 * @property string|null $board_check_config
 * @property string|null $voicemail_digest_config
 * @property string|null $wctp_config
 * @property string|null $csv_export_config
 * @property int $id
 * @property string $name
 * @property User $owner
 * @property int $user_id
 */
class Team extends JetstreamTeam
{
    use HasFactory;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];
}
