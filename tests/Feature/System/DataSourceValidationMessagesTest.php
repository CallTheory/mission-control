<?php

declare(strict_types=1);

namespace Tests\Feature\System;

use App\Enums\Capability;
use App\Livewire\System\DataSources\Intelligent;
use App\Livewire\System\DataSources\IsUser;
use App\Livewire\System\DataSources\IsWebApi;
use App\Models\DataSource;
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
            ->fillForm([
                'is_agent_username' => '',
                'is_agent_password' => $secret,
                'is_agent_password_confirmation' => 'something-else',
            ])
            ->call('save');

        $component->assertHasFormErrors(['is_agent_password']);

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
            ->fillForm([
                'is_db_host' => 'sql.example.test',
                'is_db_port' => '1433',
                'is_db_data' => 'intelligent',
                'is_db_user' => 'sa',
                'is_db_pass' => $secret,
                'is_db_pass_confirmation' => 'mismatch',
            ])
            ->call('save');

        $component->assertHasFormErrors(['is_db_pass']);

        foreach ($component->errors()->all() as $message) {
            $this->assertStringNotContainsString($secret, $message,
                'A validation message echoed the submitted database password back to the user.');
        }
    }

    public function test_a_stored_password_is_never_sent_to_the_browser(): void
    {
        // preservedFields() blanks these on load, so the credential is not in the
        // rendered DOM or the Livewire snapshot even for an authorised admin.
        DataSource::create([
            'is_db_host' => 'sql.example.test',
            'is_db_pass' => 'stored-db-secret',
        ]);

        Livewire::actingAs($this->admin(Capability::SystemDataSources))
            ->test(Intelligent::class)
            ->assertFormSet(['is_db_pass' => ''])
            ->assertDontSee('stored-db-secret');
    }

    public function test_a_failed_url_validation_explains_itself(): void
    {
        $typed = 'not-a-url';

        $component = Livewire::actingAs($this->admin(Capability::SystemDataSources))
            ->test(IsWebApi::class)
            ->fillForm(['is_web_api_endpoint' => $typed])
            ->call('save');

        $component->assertHasFormErrors(['is_web_api_endpoint']);

        $messages = $component->errors()->get('data.is_web_api_endpoint');

        // The old behaviour made the message *be* the typed value.
        $this->assertNotSame([$typed], $messages);
        $this->assertStringNotContainsString($typed, $messages[0]);
        // ...and it names the field the way the form labels it, not as a state path.
        $this->assertStringContainsString('Intelligent Series web API endpoint', $messages[0]);
    }
}
