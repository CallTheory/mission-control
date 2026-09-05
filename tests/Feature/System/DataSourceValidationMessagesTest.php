<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Enums\Capability;
use App\Livewire\System\DataSources\Intelligent;
use App\Livewire\System\DataSources\IsUser;
use App\Livewire\System\DataSources\IsWebApi;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * The DataSource settings forms used to pass their own field *values* as Laravel's
 * custom validation *messages* -- validate($rules, $messages, $attributes) with the
 * middle array filled in with $this->state[...]. On a failed validation the message
 * rendered back to the user was whatever they had typed, which for the database and
 * IS user forms meant the plaintext password appeared in the page.
 *
 * These assert the messages are real messages and never echo the submitted secret.
 */
class DataSourceValidationMessagesTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    private function admin(Capability $capability): User
    {
        // Sanity: the acting user genuinely holds the capability, so a failure here is
        // about validation rather than authorization.
        $user = $this->createUserWithRole($this->team, 'admin');
        $this->assertTrue($user->hasCapability($capability));

        return $user;
    }

    public function test_is_user_form_does_not_echo_the_submitted_password(): void
    {
        $secret = 'sup3r-s3cret-value';

        $component = Livewire::actingAs($this->admin(Capability::SystemDataSources))
            ->test(IsUser::class)
            ->set('state.is_username', '')
            ->set('state.is_password', $secret)
            ->set('state.is_password_confirmation', 'something-else')
            ->call('saveIntelligentUser');

        $component->assertHasErrors('state.is_password');

        $errors = $component->errors()->all();

        foreach ($errors as $message) {
            $this->assertStringNotContainsString($secret, $message,
                'A validation message echoed the submitted password back to the user.');
        }
    }

    public function test_intelligent_form_does_not_echo_the_submitted_database_password(): void
    {
        $secret = 'db-p4ssw0rd-should-not-appear';

        $component = Livewire::actingAs($this->admin(Capability::SystemDataSources))
            ->test(Intelligent::class)
            ->set('state.is_db_host', 'sql.example.test')
            ->set('state.is_db_port', '1433')
            ->set('state.is_db_data', 'intelligent')
            ->set('state.is_db_user', 'sa')
            ->set('state.is_db_pass', $secret)
            ->set('state.is_db_pass_confirmation', 'mismatch')
            ->call('saveIntelligentConnection');

        $component->assertHasErrors('state.is_db_pass');

        foreach ($component->errors()->all() as $message) {
            $this->assertStringNotContainsString($secret, $message,
                'A validation message echoed the submitted database password back to the user.');
        }
    }

    public function test_a_failed_url_validation_explains_itself(): void
    {
        $typed = 'not-a-url';

        $component = Livewire::actingAs($this->admin(Capability::SystemDataSources))
            ->test(IsWebApi::class)
            ->set('state.isweb_api_endpoint', $typed)
            ->call('saveISWebAPIConnection');

        $component->assertHasErrors('state.isweb_api_endpoint');

        $messages = $component->errors()->get('state.isweb_api_endpoint');

        // The old behaviour made the message *be* the typed value.
        $this->assertNotSame([$typed], $messages);
        $this->assertStringNotContainsString($typed, $messages[0]);
        // ...and it names the field the way the form labels it, not as a state path.
        $this->assertStringContainsString('Intelligent Series web API endpoint', $messages[0]);
    }
}
