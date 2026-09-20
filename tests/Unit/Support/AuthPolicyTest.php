<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two authentication policies, in isolation from how they are enforced.
 */
class AuthPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AuthPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AuthPolicy;
    }

    /**
     * saml2_enabled is not in Settings::$fillable, so it has to be set on the
     * instance rather than passed to create() -- mass assignment drops it
     * silently, which otherwise makes the interlock test pass for the wrong
     * reason.
     */
    private function settings(array $attributes = []): Settings
    {
        $settings = new Settings;

        foreach (array_merge([
            'saml2_enabled' => 1,
            'auth_enforce_linked_sso' => true,
            'auth_require_sso_or_2fa' => false,
        ], $attributes) as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        return $settings;
    }

    private function userInTeam(array $attributes = [], bool $exempt = false): User
    {
        $user = User::factory()->create($attributes);
        $team = Team::factory()->create(['personal_team' => false, 'sso_exempt' => $exempt]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user->fresh();
    }

    public function test_a_linked_user_must_use_sso(): void
    {
        $this->settings();

        $this->assertTrue($this->policy->mustUseSso($this->userInTeam(['saml_linked_id' => 'abc'])));
    }

    public function test_an_unlinked_user_is_never_forced(): void
    {
        // The case an admin will ask about: a local-only admin account is
        // untouched by enforcement no matter what the settings say.
        $this->settings();

        $this->assertFalse($this->policy->mustUseSso($this->userInTeam(['saml_linked_id' => null])));
    }

    public function test_enforcement_off_means_no_lock(): void
    {
        $this->settings(['auth_enforce_linked_sso' => false]);

        $this->assertFalse($this->policy->mustUseSso($this->userInTeam(['saml_linked_id' => 'abc'])));
    }

    public function test_disabling_saml_lifts_the_lock(): void
    {
        // The interlock. Without it, turning SAML off would strand every linked
        // user behind a login they can no longer complete.
        $this->settings(['saml2_enabled' => 0]);

        $this->assertFalse($this->policy->mustUseSso($this->userInTeam(['saml_linked_id' => 'abc'])));
    }

    public function test_an_exempt_team_lifts_the_lock(): void
    {
        $this->settings();

        $this->assertFalse($this->policy->mustUseSso($this->userInTeam(['saml_linked_id' => 'abc'], exempt: true)));
    }

    public function test_one_non_exempt_team_is_enough_to_enforce(): void
    {
        // Most restrictive wins: being added to a third-party team must not
        // loosen the requirement on the user's other teams.
        $this->settings();

        $user = $this->userInTeam(['saml_linked_id' => 'abc'], exempt: true);
        $user->teams()->attach(Team::factory()->create(['personal_team' => false, 'sso_exempt' => false]), ['role' => 'admin']);

        $this->assertTrue($this->policy->mustUseSso($user->fresh()));
    }

    public function test_no_settings_row_enforces_nothing(): void
    {
        $this->assertFalse($this->policy->mustUseSso($this->userInTeam(['saml_linked_id' => 'abc'])));
    }

    public function test_enrollment_is_needed_without_a_link_or_two_factor(): void
    {
        $this->settings(['auth_require_sso_or_2fa' => true]);

        $this->assertTrue($this->policy->needsTwoFactorEnrollment($this->userInTeam(['saml_linked_id' => null])));
    }

    public function test_a_linked_user_satisfies_the_policy(): void
    {
        $this->settings(['auth_require_sso_or_2fa' => true]);

        $this->assertFalse($this->policy->needsTwoFactorEnrollment($this->userInTeam(['saml_linked_id' => 'abc'])));
    }

    public function test_two_factor_satisfies_the_policy(): void
    {
        $this->settings(['auth_require_sso_or_2fa' => true]);

        $user = $this->userInTeam(['saml_linked_id' => null, 'two_factor_secret' => encrypt('secret')]);

        $this->assertFalse($this->policy->needsTwoFactorEnrollment($user));
    }

    public function test_the_policy_off_asks_nothing_of_anyone(): void
    {
        $this->settings(['auth_require_sso_or_2fa' => false]);

        $this->assertFalse($this->policy->needsTwoFactorEnrollment($this->userInTeam(['saml_linked_id' => null])));
    }

    public function test_an_exempt_team_still_needs_two_factor(): void
    {
        // The point of exempting a team: SSO is lifted, 2FA is not.
        $this->settings(['auth_require_sso_or_2fa' => true]);

        $user = $this->userInTeam(['saml_linked_id' => null], exempt: true);

        $this->assertFalse($this->policy->mustUseSso($user));
        $this->assertTrue($this->policy->needsTwoFactorEnrollment($user));
    }
}
