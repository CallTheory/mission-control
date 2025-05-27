<?php

namespace App\Models;

use Exception;
use App\Models\Stats\Agents\Agent;
use App\Models\Stats\BoardCheck\Activity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;

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
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
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
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    protected function defaultProfilePhotoUrl(): Application|string|UrlGenerator
    {
        return url('/images/mission-control.png');
    }

    /**
     * @throws Exception
     */
    public function getIntelligentAgent(): array|null
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
