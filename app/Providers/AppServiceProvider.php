<?php

namespace App\Providers;

use App\Extensions\SafeSaml2Provider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
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
        $this->registerFilamentColors();

        RateLimiter::for('ringcentral', function (object $job) {
            return Limit::perMinute(10);
        });

        // Override the default SAML2 provider with our PHP 8.4 compatible version
        // This fixes the strict type issue where getFirstAssertion() can return null
        Socialite::extend('saml2', function ($app) {
            $config = $app['config']['services.saml2'];

            return (new SafeSaml2Provider($app['request']))->setConfig($config);
        });
    }

    /**
     * Point Filament's semantic colours at the same palette the app's design tokens
     * use (resources/css/app.css), so Filament tables and forms inherit Mission
     * Control's look instead of shipping their own. Filament defaults `primary` to
     * amber and `gray` to zinc; both would clash with the token system.
     */
    private function registerFilamentColors(): void
    {
        FilamentColor::register([
            'primary' => Color::Indigo,
            'gray' => Color::Gray,
            'danger' => Color::Red,
            'success' => Color::Green,
            'warning' => Color::Amber,
            'info' => Color::Blue,
        ]);
    }
}
