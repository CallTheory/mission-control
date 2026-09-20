<?php

namespace App\Models;

use App\Actions\Roles\SeedDefaultRolesForTeam;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * @property bool $personal_team
 * @property bool $sso_exempt
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
 * @property string|null $better_emails_config
 * @property string|null $recording_prefix
 * @property string|null $allowed_accounts
 * @property string|null $allowed_billing
 * @property bool $unrestricted_accounts
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
        'sso_exempt' => 'boolean',
        'unrestricted_accounts' => 'boolean',
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

    /**
     * Whether this team is scoped to a subset of accounts.
     *
     * A list of whitespace counts as no list: it reads as configured but filters
     * nothing, so the runtime checks would treat it as unrestricted anyway.
     */
    public function hasAccountAllowList(): bool
    {
        return trim((string) $this->allowed_accounts) !== ''
            || trim((string) $this->allowed_billing) !== '';
    }

    /**
     * Whether this team's account scope has been decided at all -- either it is
     * restricted to a list, or someone has explicitly marked it unrestricted.
     *
     * A team that is neither is not configured, and account-scoped call data is
     * withheld from it rather than shown in full. Personal teams are exempt: they are
     * scoped by agent id, never by account, so there is nothing here to configure.
     */
    public function hasDecidedAccountScope(): bool
    {
        return $this->personal_team === true
            || $this->hasAccountAllowList()
            || $this->unrestricted_accounts === true;
    }

    /**
     * The admin-editable roles defined for this team.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    protected static function booted(): void
    {
        // Every non-personal team gets the default role definitions so that
        // membership roles (team_user.role) and the RoleManager UI resolve to
        // capabilities from the moment the team exists.
        static::created(function (Team $team) {
            if (! $team->personal_team) {
                (new SeedDefaultRolesForTeam)($team);
            }
        });
    }
}
