<?php

namespace App\Providers;

use App\Extensions\SafeSaml2Provider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('transcriptions', function (object $job) {
            return Limit::perMinute(1);
        });

        // Override the default SAML2 provider with our PHP 8.4 compatible version
        // This fixes the strict type issue where getFirstAssertion() can return null
        Socialite::extend('saml2', function ($app) {
            $config = $app['config']['services.saml2'];
            
            return (new SafeSaml2Provider($app['request']))->setConfig($config);
        });
    }
}
