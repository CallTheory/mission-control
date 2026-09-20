<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Linked accounts are refused at the login form.
 *
 * The rule has to bite before *either* credential path in
 * App\Actions\AuthenticateLoginAttempt. There are two: the local password hash,
 * and an ISWeb agent login that authenticates against the Amtelco IS Web API
 * and never consults the local hash at all. Closing only the first would leave
 * a linked account reachable with its Amtelco agent password -- which is the
 * regression worth guarding, because every SAML sign-in rotates the local
 * password to random bytes and makes the first path look closed already.
 */
class SsoEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** The password User::factory() creates accounts with. */
    private const FACTORY_PASSWORD = '0lq^V^g3CFk^';

    private function enforceSso(bool $samlEnabled = true): void
    {
        $settings = new Settings;
        $settings->saml2_enabled = $samlEnabled ? 1 : 0;
        $settings->auth_enforce_linked_sso = true;
        $settings->save();
    }

    private function user(?string $link, bool $exemptTeam = false): User
    {
        $user = User::factory()->create(['saml_linked_id' => $link]);
        $team = Team::factory()->create(['personal_team' => false, 'sso_exempt' => $exemptTeam]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user->fresh();
    }

    public function test_a_linked_account_is_refused_with_a_clear_message(): void
    {
        $this->enforceSso();
        $user = $this->user('idp-subject');

        $response = $this->post('/login', ['email' => $user->email, 'password' => self::FACTORY_PASSWORD]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'single sign-on',
            session('errors')->first('email'),
            'The user should be told why, not given the generic credentials error.'
        );
    }

    public function test_the_isweb_agent_path_is_refused_too(): void
    {
        // The account has an agtId and a password that does NOT match the local
        // hash -- exactly the shape that previously fell through to ISWeb agent
        // login. Enforcement must reject before that fallback is reached.
        $this->enforceSso();

        $user = User::factory()->create(['saml_linked_id' => 'idp-subject', 'agtId' => 4242]);
        $team = Team::factory()->create(['personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'the-amtelco-agent-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_an_unlinked_account_is_unaffected(): void
    {
        // The reassurance an admin needs: a local-only account still signs in
        // normally while enforcement is on.
        $this->enforceSso();
        $user = $this->user(null);

        $response = $this->post('/login', ['email' => $user->email, 'password' => self::FACTORY_PASSWORD]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_an_exempt_team_may_still_use_a_password(): void
    {
        $this->enforceSso();
        $user = $this->user('idp-subject', exemptTeam: true);

        $this->post('/login', ['email' => $user->email, 'password' => self::FACTORY_PASSWORD]);

        $this->assertAuthenticated();
    }

    public function test_enforcement_off_changes_nothing(): void
    {
        $user = $this->user('idp-subject');

        $this->post('/login', ['email' => $user->email, 'password' => self::FACTORY_PASSWORD]);

        $this->assertAuthenticated();
    }

    public function test_disabling_saml_lets_linked_accounts_back_in(): void
    {
        // The interlock, end to end: turning SAML off must not strand anyone.
        $this->enforceSso(samlEnabled: false);
        $user = $this->user('idp-subject');

        $this->post('/login', ['email' => $user->email, 'password' => self::FACTORY_PASSWORD]);

        $this->assertAuthenticated();
    }
}
