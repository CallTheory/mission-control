<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * sso:unlink is the break-glass for an identity provider that has been rebuilt
 * and reissued every subject id. Sign-in binds on saml_linked_id, so a stale
 * link locks the account out -- and this is the only way back. It is the last
 * command anyone should discover is broken, hence the coverage.
 */
class SsoUnlinkTest extends TestCase
{
    use RefreshDatabase;

    private function linkedUser(string $email, string $subject = 'idp-subject-1'): User
    {
        return User::factory()->create(['email' => $email, 'saml_linked_id' => $subject]);
    }

    public function test_it_clears_one_account_by_email(): void
    {
        $user = $this->linkedUser('linked@example.com');
        $other = $this->linkedUser('untouched@example.com', 'idp-subject-2');

        $this->artisan('sso:unlink', ['email' => 'linked@example.com'])
            ->expectsOutputToContain('Cleared the SAML link on linked@example.com.')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->saml_linked_id);
        $this->assertSame('idp-subject-2', $other->fresh()->saml_linked_id, 'Only the named account should change.');
    }

    public function test_all_clears_every_linked_account(): void
    {
        $this->linkedUser('one@example.com', 'a');
        $this->linkedUser('two@example.com', 'b');
        $unlinked = User::factory()->create(['saml_linked_id' => null]);

        $this->artisan('sso:unlink', ['--all' => true])
            ->expectsConfirmation('Clear the SAML link on 2 account(s)?', 'yes')
            ->assertSuccessful();

        $this->assertSame(0, User::whereNotNull('saml_linked_id')->count());
        $this->assertNull($unlinked->fresh()->saml_linked_id);
    }

    public function test_all_can_be_declined_at_the_prompt(): void
    {
        $user = $this->linkedUser('keep@example.com');

        $this->artisan('sso:unlink', ['--all' => true])
            ->expectsConfirmation('Clear the SAML link on 1 account(s)?', 'no')
            ->expectsOutputToContain('Nothing changed.')
            ->assertSuccessful();

        $this->assertSame('idp-subject-1', $user->fresh()->saml_linked_id);
    }

    public function test_it_refuses_both_an_email_and_all(): void
    {
        $user = $this->linkedUser('linked@example.com');

        $this->artisan('sso:unlink', ['email' => 'linked@example.com', '--all' => true])
            ->expectsOutputToContain('Pass either an email address or --all, not both or neither.')
            ->assertFailed();

        $this->assertNotNull($user->fresh()->saml_linked_id);
    }

    public function test_it_refuses_neither_an_email_nor_all(): void
    {
        $this->artisan('sso:unlink')
            ->expectsOutputToContain('Pass either an email address or --all, not both or neither.')
            ->assertFailed();
    }

    public function test_it_reports_an_unknown_email(): void
    {
        $this->artisan('sso:unlink', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('No account found for nobody@example.com.')
            ->assertFailed();
    }

    public function test_it_is_a_no_op_on_an_already_unlinked_account(): void
    {
        $user = User::factory()->create(['email' => 'plain@example.com', 'saml_linked_id' => null]);

        $this->artisan('sso:unlink', ['email' => 'plain@example.com'])
            ->expectsOutputToContain('plain@example.com is not linked.')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->saml_linked_id);
    }

    public function test_all_says_so_when_nothing_is_linked(): void
    {
        User::factory()->create(['saml_linked_id' => null]);

        $this->artisan('sso:unlink', ['--all' => true])
            ->expectsOutputToContain('No accounts are linked.')
            ->assertSuccessful();
    }
}
