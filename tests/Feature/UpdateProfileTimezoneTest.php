<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\User;
use App\Support\TimezoneOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UpdateProfileTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_profile_form_alias_resolves_to_our_subclass(): void
    {
        $this->actingAs(User::factory()->create(['timezone' => 'America/Chicago']));

        $component = Livewire::test('profile.update-profile-information-form')->assertOk();

        $this->assertInstanceOf(UpdateProfileInformationForm::class, $component->instance());

        $component->assertSet('data.timezone', 'America/Chicago');
    }

    public function test_timezone_can_be_changed(): void
    {
        $this->actingAs($user = User::factory()->create(['timezone' => 'America/Chicago']));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.name', $user->name)
            ->set('state.email', $user->email)
            ->set('data.timezone', 'America/Denver')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('America/Denver', $user->fresh()->timezone);
    }

    public function test_timezone_survives_an_email_change(): void
    {
        $this->actingAs($user = User::factory()->create(['timezone' => 'America/Chicago']));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.name', $user->name)
            ->set('state.email', 'moved@example.com')
            ->set('data.timezone', 'America/New_York')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user = $user->fresh();

        $this->assertSame('moved@example.com', $user->email);
        $this->assertSame('America/New_York', $user->timezone);
    }

    public function test_an_invalid_timezone_is_rejected(): void
    {
        $this->actingAs($user = User::factory()->create(['timezone' => 'America/Chicago']));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.name', $user->name)
            ->set('state.email', $user->email)
            ->set('data.timezone', null)
            ->call('updateProfileInformation')
            ->assertHasErrors('data.timezone');

        $this->assertSame('America/Chicago', $user->fresh()->timezone);
    }

    public function test_the_profile_page_renders_the_select_with_the_current_zone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Denver']);

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk();

        // The Filament Select renders, rather than the old <datalist> input.
        $response->assertSee('fi-fo-select', escape: false);
        $response->assertDontSee('id="timezone_list"', escape: false);

        // And it carries this user's zone, not the first option in the list. A
        // searchable Select resolves its label client-side, so the value reaches
        // the page JSON-escaped inside the Livewire snapshot.
        $response->assertSee('America\\/Denver', escape: false);
    }

    public function test_options_are_grouped_and_never_repeat_an_identifier(): void
    {
        $grouped = TimezoneOptions::grouped();

        $this->assertArrayHasKey('United States', $grouped);
        $this->assertArrayHasKey('Canada', $grouped);
        $this->assertSame('Chicago · Central', $grouped['United States']['America/Chicago']);
        $this->assertSame('Vancouver · Pacific', $grouped['Canada']['America/Vancouver']);

        $all = array_merge(...array_values(array_map('array_keys', $grouped)));

        $this->assertSame(array_unique($all), $all, 'an identifier appears in more than one group');
        $this->assertContains('Europe/London', $all);
        $this->assertContains('UTC', $all);
    }
}
