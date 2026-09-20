<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The SSO-or-2FA policy, enforced after login rather than at it.
 *
 * A user who has not set 2FA up yet still has to get in far enough to set it
 * up, so EnsureAuthPolicy redirects instead of blocking, and leaves the
 * enrolment routes reachable.
 */
class TwoFactorPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function requirePolicy(bool $on = true): void
    {
        $settings = new Settings;
        $settings->saml2_enabled = 1;
        $settings->auth_require_sso_or_2fa = $on;
        $settings->save();
    }

    private function user(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $team = Team::factory()->create(['personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user->fresh();
    }

    public function test_a_user_with_neither_is_sent_to_set_up_two_factor(): void
    {
        $this->requirePolicy();
        $user = $this->user(['saml_linked_id' => null]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('profile.show'));
    }

    public function test_the_profile_page_stays_reachable(): void
    {
        // Otherwise the redirect target would itself redirect, and the policy
        // would be unsatisfiable.
        $this->requirePolicy();
        $user = $this->user(['saml_linked_id' => null]);

        $this->actingAs($user)->get(route('profile.show'))->assertOk();
    }

    public function test_logout_stays_reachable(): void
    {
        $this->requirePolicy();
        $user = $this->user(['saml_linked_id' => null]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect();
    }

    public function test_a_user_with_two_factor_passes_through(): void
    {
        $this->requirePolicy();
        $user = $this->user(['saml_linked_id' => null, 'two_factor_secret' => encrypt('secret')]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_a_linked_user_passes_through(): void
    {
        $this->requirePolicy();
        $user = $this->user(['saml_linked_id' => 'idp-subject']);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_the_policy_off_lets_everyone_through(): void
    {
        $this->requirePolicy(false);
        $user = $this->user(['saml_linked_id' => null]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_guests_are_untouched(): void
    {
        $this->requirePolicy();

        $this->get('/login')->assertOk();
    }
}
