<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Livewire\Profile\UserTheme;
use App\Livewire\System\DataSources\ClientDb;
use App\Livewire\System\Integrations\Twilio;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_twilio_hydrates_state_from_datasource_via_trait(): void
    {
        DataSource::create([
            'twilio_account_sid' => 'AC-existing',
            'twilio_auth_token' => 'tok-existing',
            'twilio_from_number' => '+15551112222',
        ]);

        Livewire::test(Twilio::class)
            ->assertSet('state.twilio_account_sid', 'AC-existing')
            ->assertSet('state.twilio_auth_token', 'tok-existing')
            ->assertSet('state.twilio_from_number', '+15551112222');
    }

    public function test_twilio_save_persists_plaintext_and_closes_modal(): void
    {
        DataSource::create(['twilio_from_number' => '+15550000000']);

        Livewire::test(Twilio::class)
            ->set('isOpen', true)
            ->set('state.twilio_account_sid', 'AC-new')
            ->set('state.twilio_auth_token', 'tok-new')
            ->set('state.twilio_from_number', '+15559998888')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('isOpen', false)
            ->assertDispatched('saved');

        $ds = DataSource::first();
        // Model cast returns plaintext; stored ciphertext round-trips.
        $this->assertSame('AC-new', $ds->twilio_account_sid);
        $this->assertSame('tok-new', $ds->twilio_auth_token);
        $this->assertSame('+15559998888', $ds->twilio_from_number);

        // Encrypted at rest.
        $raw = \DB::table('data_sources')->where('id', $ds->id)->value('twilio_auth_token');
        $this->assertNotSame('tok-new', $raw);
    }

    public function test_twilio_blank_field_persists_as_null(): void
    {
        DataSource::create(['twilio_from_number' => '+15550000000']);

        Livewire::test(Twilio::class)
            ->set('state.twilio_account_sid', '')
            ->set('state.twilio_auth_token', '')
            ->set('state.twilio_from_number', '')
            ->call('save')
            ->assertHasNoErrors();

        $ds = DataSource::first();
        $this->assertNull($ds->twilio_account_sid);
        $this->assertNull($ds->twilio_from_number);
    }

    public function test_client_db_form_renders(): void
    {
        DataSource::create(['client_db_host' => 'db.example', 'client_db_port' => '1433']);

        Livewire::test(ClientDb::class)
            ->assertSuccessful()
            ->assertSet('state.client_db_host', 'db.example')
            ->assertSet('state.client_db_pass', ''); // password never prefilled
    }

    public function test_user_theme_toggle_persists_dark_mode(): void
    {
        $user = User::factory()->create(['dark_mode' => null]);
        $this->actingAs($user);

        Livewire::test(UserTheme::class)
            ->set('state.user_theme', 'dark')
            ->call('updateUserTheme')
            ->assertDispatched('saved');

        $this->assertSame('dark', $user->fresh()->dark_mode);
    }
}
