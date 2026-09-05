<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\System\Integrations\PeoplePraise;
use App\Livewire\System\Integrations\Stripe;
use App\Livewire\System\Integrations\Twilio;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * The integration tiles after moving from a hand-rolled dialog to a Filament action
 * with a schema. What matters is unchanged: the right columns are written, secrets
 * round-trip through the model's encryption casts, and the capability still gates it.
 */
class IntegrationSettingsTest extends TestCase
{
    use CreatesTeamUsers;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();
    }

    private function admin(): User
    {
        return $this->createUserWithRole($this->team, 'admin');
    }

    public function test_twilio_saves_its_credentials(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Twilio::class)
            ->callAction('configure', [
                'twilio_account_sid' => 'ACabc123',
                'twilio_auth_token' => 'super-secret-token',
                'twilio_from_number' => '+15551234567',
            ])
            ->assertHasNoErrors();

        $datasource = DataSource::first();

        $this->assertSame('ACabc123', $datasource->twilio_account_sid);
        $this->assertSame('super-secret-token', $datasource->twilio_auth_token);
        $this->assertSame('+15551234567', $datasource->twilio_from_number);

        // The cast returns plaintext; what lands in the column must not be.
        $this->assertNotSame(
            'super-secret-token',
            $datasource->getRawOriginal('twilio_auth_token'),
            'The Twilio auth token was stored in plaintext.'
        );
    }

    public function test_twilio_reports_whether_it_is_fully_configured(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Twilio::class);
        $this->assertFalse($component->instance()->isConfigured());

        $component->callAction('configure', [
            'twilio_account_sid' => 'ACabc123',
            'twilio_auth_token' => 'tok',
            'twilio_from_number' => '+15551234567',
        ]);

        $this->assertTrue(
            Livewire::actingAs($this->admin())->test(Twilio::class)->instance()->isConfigured()
        );
    }

    public function test_a_blank_field_is_stored_as_null_rather_than_an_empty_string(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Twilio::class)
            ->callAction('configure', [
                'twilio_account_sid' => 'ACabc123',
                'twilio_auth_token' => '',
                'twilio_from_number' => '',
            ]);

        $datasource = DataSource::first();

        $this->assertNull($datasource->twilio_auth_token);
        $this->assertNull($datasource->twilio_from_number);
    }

    public function test_the_form_opens_prefilled_with_what_is_stored(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Twilio::class)
            ->callAction('configure', [
                'twilio_account_sid' => 'ACpersisted',
                'twilio_auth_token' => 'tok',
                'twilio_from_number' => '+15550000000',
            ]);

        Livewire::actingAs($this->admin())
            ->test(Twilio::class)
            ->mountAction('configure')
            ->assertActionDataSet(['twilio_account_sid' => 'ACpersisted']);
    }

    public function test_stripe_writes_the_columns_its_fields_are_named_after(): void
    {
        // The previous form used state keys that transposed the column names
        // (stripe_secret_test_key for the stripe_test_secret_key column).
        Livewire::actingAs($this->admin())
            ->test(Stripe::class)
            ->callAction('configure', [
                'stripe_test_secret_key' => 'sk_test_abc',
                'stripe_prod_secret_key' => 'sk_live_xyz',
            ])
            ->assertHasNoErrors();

        $datasource = DataSource::first();

        $this->assertSame('sk_test_abc', $datasource->stripe_test_secret_key);
        $this->assertSame('sk_live_xyz', $datasource->stripe_prod_secret_key);
    }

    public function test_stripe_keys_are_encrypted_at_rest(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Stripe::class)
            ->callAction('configure', [
                'stripe_test_secret_key' => 'sk_test_abc',
                'stripe_prod_secret_key' => 'sk_live_xyz',
            ]);

        $raw = DataSource::first()->getRawOriginal('stripe_prod_secret_key');

        $this->assertNotSame('sk_live_xyz', $raw, 'The live key was stored in plaintext.');
    }

    public function test_people_praise_saves_its_credentials(): void
    {
        Livewire::actingAs($this->admin())
            ->test(PeoplePraise::class)
            ->callAction('configure', [
                'people_praise_basic_auth_user' => 'exporter',
                'people_praise_basic_auth_pass' => 'hunter2',
            ])
            ->assertHasNoErrors();

        $datasource = DataSource::first();

        $this->assertSame('exporter', $datasource->people_praise_basic_auth_user);
        $this->assertSame('hunter2', $datasource->people_praise_basic_auth_pass);
    }

    public function test_a_user_without_the_capability_cannot_save_any_of_them(): void
    {
        DataSource::create(['twilio_account_sid' => 'AC-original']);

        $denied = $this->createUserWithout($this->team, 'admin', Capability::SystemIntegrations);

        foreach ([Twilio::class, Stripe::class, PeoplePraise::class] as $component) {
            try {
                Livewire::actingAs($denied)
                    ->test($component)
                    ->callAction('configure', ['twilio_account_sid' => 'AC-hacked']);
            } catch (\Throwable) {
                // Livewire renders the authorization failure rather than rethrowing it
                // cleanly; the security property under test is that no write happened.
            }
        }

        $this->assertSame('AC-original', DataSource::first()->twilio_account_sid);
    }
}
