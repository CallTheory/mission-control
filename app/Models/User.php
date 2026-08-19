<?php

namespace App\Models;

use App\Enums\Capability;
use App\Models\Stats\Agents\Agent;
use App\Models\Stats\BoardCheck\Activity;
use Exception;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use stdClass;
use Throwable;

/**
 * @property Team|null $currentTeam
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $agtId
 * @property string|null $timezone
 * @property string|null $saml_linked_id
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Per-request memo of resolved capabilities, keyed by team id.
     *
     * @var array<int, Collection<int, string>>
     */
    protected array $capabilityCache = [];

    protected ?string $resolvedAgentName = null;

    protected bool $agentNameResolved = false;

    protected function defaultProfilePhotoUrl(): Application|string|UrlGenerator
    {
        return url('/images/mission-control.png');
    }

    /**
     * All roles assigned to this user across every team.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * The roles this user holds on the given team.
     *
     * @return Collection<int, Role>
     */
    public function rolesForTeam(?Team $team = null): Collection
    {
        $team = $team ?: $this->currentTeam;

        if ($team === null) {
            return collect();
        }

        return $this->roles()
            ->where('roles.team_id', $team->id)
            ->with('capabilities')
            ->get();
    }

    /**
     * Whether the user has the given capability on the given team (defaults to
     * the current team). The capability set is the union of every role the user
     * holds on that team plus any suffix-rule grants.
     */
    public function hasCapability(Capability|string $capability, ?Team $team = null): bool
    {
        $value = $capability instanceof Capability ? $capability->value : $capability;

        return $this->capabilitiesFor($team)->contains($value);
    }

    /**
     * The resolved, de-duplicated capability values for a team (memoized).
     *
     * @return Collection<int, string>
     */
    public function capabilitiesFor(?Team $team = null): Collection
    {
        $team = $team ?: $this->currentTeam;

        if ($team === null) {
            return collect();
        }

        return $this->capabilityCache[$team->id] ??= $this->roleCapabilitiesFor($team)
            ->merge($this->suffixCapabilities($team))
            ->unique()
            ->values();
    }

    /**
     * Capabilities from the user's roles on a team. Explicit role_user
     * assignments (the multi-role admin UI) win; when a user has none we fall
     * back to Jetstream's single team_user.role pivot so members added through
     * Jetstream's invite/add-member flow still resolve to capabilities.
     *
     * @return Collection<int, string>
     */
    protected function roleCapabilitiesFor(Team $team): Collection
    {
        $explicit = $this->rolesForTeam($team);

        if ($explicit->isNotEmpty()) {
            return $explicit->flatMap(fn (Role $role) => $role->capabilities->pluck('capability'));
        }

        $pivotRole = DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', $this->id)
            ->value('role');

        if ($pivotRole === null) {
            return collect();
        }

        $role = Role::where('team_id', $team->id)
            ->where('key', $pivotRole)
            ->with('capabilities')
            ->first();

        return $role ? $role->capabilities->pluck('capability') : collect();
    }

    /**
     * Capabilities granted by suffix rules matching this user's linked
     * Intelligent agent name (global rules plus this team's rules).
     *
     * @return Collection<int, string>
     */
    public function suffixCapabilities(?Team $team = null): Collection
    {
        $team = $team ?: $this->currentTeam;

        if ($team === null) {
            return collect();
        }

        $name = $this->intelligentAgentName();

        if ($name === '') {
            return collect();
        }

        return SuffixRule::query()
            ->where(fn ($q) => $q->whereNull('team_id')->orWhere('team_id', $team->id))
            ->get()
            ->filter(fn (SuffixRule $rule) => $rule->matches($name))
            ->pluck('capability')
            ->unique()
            ->values();
    }

    public function assignRole(Role $role): void
    {
        $this->roles()->syncWithoutDetaching([$role->id]);
        unset($this->capabilityCache[$role->team_id]);
    }

    public function removeRole(Role $role): void
    {
        $this->roles()->detach($role->id);
        unset($this->capabilityCache[$role->team_id]);
    }

    /**
     * The linked Intelligent agent's Name, resolved once per request. Failures
     * (e.g. the external phone system is unreachable) degrade to an empty name.
     */
    protected function intelligentAgentName(): string
    {
        if (! $this->agentNameResolved) {
            $this->agentNameResolved = true;

            try {
                $agent = $this->getIntelligentAgent();
                $this->resolvedAgentName = is_object($agent) ? ($agent->Name ?? '') : '';
            } catch (Throwable $e) {
                $this->resolvedAgentName = '';
            }
        }

        return $this->resolvedAgentName ?? '';
    }

    /**
     * @throws Exception
     */
    public function getIntelligentAgent(): array|null|stdClass
    {
        if (is_null($this->agtId)) {
            return null;
        } else {
            $agent = new Agent(['agtId' => $this->agtId]);

            return $agent->results[0] ?? null;
        }
    }

    public function removeApplicationData(): void
    {
        Activity::where('user_id', $this->id)->delete();
        DB::table('password_resets')->where('email', $this->email)->delete();
        DB::table('team_invitations')->where('email', $this->email)->delete();
    }
}
