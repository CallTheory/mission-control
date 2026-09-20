<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\System\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The way back when the identity provider is down.
 *
 * With the lock on, every linked user is refused at the login form -- admins
 * included -- so the setting cannot be reached through the UI that would
 * normally change it. This command is the only route, which makes it worth
 * covering even though it is three lines of logic.
 */
class SsoEnforcementCommandTest extends TestCase
{
    use RefreshDatabase;

    private function settings(bool $enforcing, int $samlEnabled = 1): Settings
    {
        $settings = new Settings;
        $settings->saml2_enabled = $samlEnabled;
        $settings->auth_enforce_linked_sso = $enforcing;
        $settings->save();

        return $settings;
    }

    public function test_it_disables_enforcement(): void
    {
        $settings = $this->settings(enforcing: true);

        $this->artisan('sso:enforcement', ['--disable' => true])
            ->expectsOutputToContain('Linked accounts may sign in with a password again.')
            ->assertSuccessful();

        $this->assertFalse((bool) $settings->fresh()->auth_enforce_linked_sso);
    }

    public function test_it_enables_enforcement(): void
    {
        $settings = $this->settings(enforcing: false);

        $this->artisan('sso:enforcement', ['--enable' => true])
            ->expectsOutputToContain('Linked accounts must now sign in through single sign-on.')
            ->assertSuccessful();

        $this->assertTrue((bool) $settings->fresh()->auth_enforce_linked_sso);
    }

    public function test_it_reports_status_without_changing_anything(): void
    {
        $settings = $this->settings(enforcing: true);

        $this->artisan('sso:enforcement')
            ->expectsOutputToContain('Force linked accounts through SSO : on')
            ->assertSuccessful();

        $this->assertTrue((bool) $settings->fresh()->auth_enforce_linked_sso);
    }

    public function test_it_refuses_both_flags(): void
    {
        $settings = $this->settings(enforcing: true);

        $this->artisan('sso:enforcement', ['--enable' => true, '--disable' => true])
            ->expectsOutputToContain('Pass either --enable or --disable, not both.')
            ->assertFailed();

        $this->assertTrue((bool) $settings->fresh()->auth_enforce_linked_sso);
    }

    public function test_it_warns_when_the_lock_is_inactive_because_saml_is_off(): void
    {
        // Otherwise an admin turning it on with SAML disabled would believe the
        // lock was live when AuthPolicy is ignoring it.
        $this->settings(enforcing: true, samlEnabled: 0);

        $this->artisan('sso:enforcement')
            ->expectsOutputToContain('SAML is off, so the lock is inactive')
            ->assertSuccessful();
    }

    public function test_it_fails_cleanly_with_no_settings_row(): void
    {
        $this->artisan('sso:enforcement')
            ->expectsOutputToContain('No settings row exists yet')
            ->assertFailed();
    }
}
