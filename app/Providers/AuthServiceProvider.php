<?php

namespace App\Providers;

use App\Enums\Capability;
use App\Enums\Utility;
use App\Models\Stats\Helpers;
use App\Models\Team;
use App\Models\User;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Team::class => TeamPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerCapabilityGates();
    }

    /**
     * Define a Gate ability per capability so @can(...) and authorize(...) work
     * everywhere. Utility capabilities additionally enforce the system + team
     * feature-flag layers and the personal-team block, collapsing the compound
     * condition that used to be hand-written at every utility call site.
     */
    protected function registerCapabilityGates(): void
    {
        // Non-utility capabilities: a straight capability check.
        foreach (Capability::cases() as $capability) {
            if ($capability->isUtility()) {
                continue;
            }

            Gate::define(
                $capability->value,
                fn (User $user) => $user->hasCapability($capability)
            );
        }

        // Utility capabilities: system flag AND team flag AND not a personal
        // team AND the role/suffix capability.
        foreach (Utility::cases() as $utility) {
            Gate::define($utility->capability()->value, function (User $user) use ($utility) {
                $team = $user->currentTeam;

                return $team !== null
                    && $team->personal_team !== true
                    && Helpers::isSystemFeatureEnabled($utility->systemFlag())
                    && (bool) ($team->{$utility->teamColumn()})
                    && $user->hasCapability($utility->capability());
            });
        }
    }
}
