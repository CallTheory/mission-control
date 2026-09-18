<?php

namespace App\Providers;

use App\Extensions\SafeSaml2Provider;
use App\Services\FeatureFlags;
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
        // A singleton so its per-request memo actually spans the request: the
        // flag map is read many times per page.
        $this->app->singleton(FeatureFlags::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerFilamentColors();

        // Client-side throttle on outbound RingCentral fax submissions. Configurable
        // because the ceiling depends on the RingCentral account's API tier, and a wrong
        // guess shows up as 429s rather than as a clean queue wait.
        RateLimiter::for('ringcentral', function (object $job) {
            return Limit::perMinute(max(1, (int) config('services.fax.ringcentral.sends_per_minute', 10)));
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
