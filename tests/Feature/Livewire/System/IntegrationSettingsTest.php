<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\System\Integrations\Bandwidth;
use App\Livewire\System\Integrations\Commio;
use App\Livewire\System\Integrations\Mfax;
use App\Livewire\System\Integrations\PeoplePraise;
use App\Livewire\System\Integrations\Ringcentral;
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
            ->assertHasNoErrors()
            ->assertNotified('Settings saved');

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

    // ------------------------------------------------------------------
    // Bandwidth and Com.io: the two SMS carriers that joined Twilio on the WCTP
    // gateway. Both carry API credentials and, separately, the credentials a
    // carrier presents to US on an inbound webhook.
    // ------------------------------------------------------------------

    public function test_bandwidth_saves_its_api_and_callback_credentials(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Bandwidth::class)
            ->callAction('configure', [
                'bandwidth_account_id' => '5000000',
                'bandwidth_application_id' => 'app-123',
                'bandwidth_from_number' => '+15552220000',
                'bandwidth_api_token' => 'api-token',
                'bandwidth_api_secret' => 'api-secret',
                'bandwidth_callback_username' => 'hook-user',
                'bandwidth_callback_password' => 'hook-pass',
                'bandwidth_callback_token' => 'hook-token',
            ])
            ->assertHasNoErrors()
            ->assertNotified('Settings saved');

        $datasource = DataSource::first();

        $this->assertSame('5000000', $datasource->bandwidth_account_id);
        $this->assertSame('app-123', $datasource->bandwidth_application_id);
        $this->assertSame('api-secret', $datasource->bandwidth_api_secret);
        $this->assertSame('hook-pass', $datasource->bandwidth_callback_password);

        foreach (['bandwidth_api_token', 'bandwidth_api_secret', 'bandwidth_callback_password', 'bandwidth_callback_token'] as $column) {
            $this->assertNotSame(
                $datasource->{$column},
                $datasource->getRawOriginal($column),
                "{$column} was stored in plaintext."
            );
        }
    }

    public function test_bandwidth_reports_whether_it_can_send(): void
    {
        $this->assertFalse(
            Livewire::actingAs($this->admin())->test(Bandwidth::class)->instance()->isConfigured()
        );

        Livewire::actingAs($this->admin())
            ->test(Bandwidth::class)
            ->callAction('configure', [
                'bandwidth_account_id' => '5000000',
                'bandwidth_application_id' => 'app-123',
                'bandwidth_from_number' => '+15552220000',
                'bandwidth_api_token' => 'api-token',
                'bandwidth_api_secret' => 'api-secret',
            ]);

        $this->assertTrue(
            Livewire::actingAs($this->admin())->test(Bandwidth::class)->instance()->isConfigured()
        );
    }

    public function test_a_blank_secret_keeps_the_stored_one(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Bandwidth::class)->callAction('configure', [
            'bandwidth_account_id' => '5000000',
            'bandwidth_api_secret' => 'original-secret',
        ]);

        // Secrets are never rendered back into the page, so a blank submission has to
        // mean "leave it alone" rather than "clear it".
        Livewire::actingAs($admin)->test(Bandwidth::class)->callAction('configure', [
            'bandwidth_account_id' => '5000001',
            'bandwidth_api_secret' => '',
        ]);

        $datasource = DataSource::first();

        $this->assertSame('5000001', $datasource->bandwidth_account_id);
        $this->assertSame('original-secret', $datasource->bandwidth_api_secret);
    }

    public function test_a_stored_secret_is_never_prefilled_into_the_form(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Bandwidth::class)->callAction('configure', [
            'bandwidth_account_id' => '5000000',
            'bandwidth_api_secret' => 'original-secret',
        ]);

        Livewire::actingAs($admin)
            ->test(Bandwidth::class)
            ->mountAction('configure')
            ->assertActionDataSet([
                'bandwidth_account_id' => '5000000',
                'bandwidth_api_secret' => '',
            ]);
    }

    public function test_commio_saves_its_credentials(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Commio::class)
            ->callAction('configure', [
                'commio_account_id' => '4321',
                'commio_username' => 'portal-user',
                'commio_api_token' => 'portal-token',
                'commio_from_number' => '+15553330000',
                'commio_callback_token' => 'hook-token',
            ])
            ->assertHasNoErrors();

        $datasource = DataSource::first();

        $this->assertSame('4321', $datasource->commio_account_id);
        $this->assertSame('portal-user', $datasource->commio_username);
        $this->assertSame('portal-token', $datasource->commio_api_token);
        $this->assertNotSame('portal-token', $datasource->getRawOriginal('commio_api_token'));

        $this->assertTrue(
            Livewire::actingAs($this->admin())->test(Commio::class)->instance()->isConfigured()
        );
    }

    // ------------------------------------------------------------------
    // Mfax and RingCentral carry behaviour a mechanical port would have lost.
    // ------------------------------------------------------------------

    public function test_mfax_generates_inbound_credentials_on_first_view(): void
    {
        $component = Livewire::actingAs($this->admin())->test(Mfax::class);

        $credentials = $component->instance()->basicAuthCredentials();

        $this->assertNotNull($credentials['username']);
        $this->assertNotNull($credentials['password']);
    }

    public function test_mfax_keeps_the_credentials_it_already_generated(): void
    {
        $first = Livewire::actingAs($this->admin())->test(Mfax::class)
            ->instance()->basicAuthCredentials();

        $second = Livewire::actingAs($this->admin())->test(Mfax::class)
            ->instance()->basicAuthCredentials();

        $this->assertSame($first['username'], $second['username']);
        $this->assertSame($first['password'], $second['password']);
    }

    public function test_mfax_enables_itself_only_on_first_configuration(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Mfax::class)
            ->callAction('configure', ['mfax_api_key' => 'key-one']);

        $this->assertTrue((bool) DataSource::first()->mfax_enabled);

        // An operator turning it off must stay off when the key is later edited.
        DataSource::first()->forceFill(['mfax_enabled' => false])->save();

        Livewire::actingAs($admin)->test(Mfax::class)
            ->callAction('configure', ['mfax_api_key' => 'key-two']);

        $this->assertFalse((bool) DataSource::first()->mfax_enabled);
        $this->assertSame('key-two', DataSource::first()->mfax_api_key);
    }

    public function test_ringcentral_keeps_a_stored_secret_when_the_field_is_left_blank(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Ringcentral::class)
            ->callAction('configure', [
                'ringcentral_client_id' => 'client-1',
                'ringcentral_api_endpoint' => 'https://platform.ringcentral.com',
                'ringcentral_client_secret' => 'the-secret',
                'ringcentral_jwt_token' => 'the-jwt',
            ]);

        // Re-save with the secret fields blank, as the dialog invites.
        Livewire::actingAs($admin)->test(Ringcentral::class)
            ->callAction('configure', [
                'ringcentral_client_id' => 'client-2',
                'ringcentral_api_endpoint' => 'https://platform.ringcentral.com',
                'ringcentral_client_secret' => '',
                'ringcentral_jwt_token' => '',
            ]);

        $datasource = DataSource::first();

        $this->assertSame('client-2', $datasource->ringcentral_client_id);
        $this->assertSame('the-secret', $datasource->ringcentral_client_secret);
        $this->assertSame('the-jwt', $datasource->ringcentral_jwt_token);
    }

    public function test_ringcentral_never_sends_a_stored_secret_to_the_browser(): void
    {
        Livewire::actingAs($this->admin())->test(Ringcentral::class)
            ->callAction('configure', [
                'ringcentral_client_id' => 'client-1',
                'ringcentral_client_secret' => 'the-secret',
                'ringcentral_jwt_token' => 'the-jwt',
            ]);

        Livewire::actingAs($this->admin())
            ->test(Ringcentral::class)
            ->mountAction('configure')
            ->assertActionDataSet(['ringcentral_client_secret' => '', 'ringcentral_jwt_token' => ''])
            ->assertDontSee('the-secret')
            ->assertDontSee('the-jwt');
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
