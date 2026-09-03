<?php

namespace App\Providers;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        // The Horizon dashboard at /queue can retry and delete jobs, so it is
        // gated like the rest of /system rather than being open to any
        // authenticated user.
        Gate::define('viewHorizon', function (?User $user) {
            return $user !== null && $user->hasCapability(Capability::SystemAccess);
        });
    }
}
