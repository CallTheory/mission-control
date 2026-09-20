<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Livewire\Profile\SsoConnection;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use App\Support\AuthPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Unlinking has to leave the account usable.
 *
 * Every SAML sign-in rotates the stored password to random bytes, so an account
 * that has only ever used SSO has no password its owner knows. Clearing the
 * link without sending a reset would strand anyone without an agtId to fall
 * back on.
 */
class SsoUnlinkFlowTest extends TestCase
{
    use RefreshDatabase;

    private function linkedUser(): User
    {
        $settings = new Settings;
        $settings->saml2_enabled = 1;
        $settings->save();

        $user = User::factory()->create(['saml_linked_id' => 'idp-subject']);
        $team = Team::factory()->create(['personal_team' => false]);
        $user->teams()->attach($team, ['role' => 'admin']);
        $user->switchTeam($team);

        return $user->fresh();
    }

    public function test_unlinking_clears_the_link(): void
    {
        Notification::fake();
        $user = $this->linkedUser();

        Livewire::actingAs($user)->test(SsoConnection::class)->call('unlink');

        $this->assertNull($user->fresh()->saml_linked_id);
    }

    public function test_unlinking_emails_a_password_reset(): void
    {
        Notification::fake();
        $user = $this->linkedUser();

        Livewire::actingAs($user)->test(SsoConnection::class)->call('unlink');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unlinking_drops_other_sessions(): void
    {
        // The suite runs SESSION_DRIVER=array (phpunit.xml), and the production
        // code correctly no-ops for any driver but database -- so the database
        // driver has to be switched on here for the real path to be exercised.
        config(['session.driver' => 'database']);

        Notification::fake();
        $user = $this->linkedUser();

        DB::table('sessions')->insert([
            ['id' => 'other-device', 'user_id' => $user->id, 'ip_address' => '127.0.0.1',
                'user_agent' => 'test', 'payload' => '', 'last_activity' => time()],
        ]);

        Livewire::actingAs($user)->test(SsoConnection::class)->call('unlink');

        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_an_unlinked_account_can_sign_in_again_under_enforcement(): void
    {
        // End to end: enforcement stays on, but unlinking releases this account.
        Notification::fake();

        // linkedUser() creates the settings row, so enforcement is switched on
        // after it rather than before.
        $user = $this->linkedUser();

        $settings = Settings::first();
        $settings->auth_enforce_linked_sso = true;
        $settings->save();

        $this->assertTrue(app(AuthPolicy::class)->mustUseSso($user), 'Precondition: locked before unlinking.');

        Livewire::actingAs($user)->test(SsoConnection::class)->call('unlink');

        $this->assertFalse(app(AuthPolicy::class)->mustUseSso($user->fresh()));
    }
}
