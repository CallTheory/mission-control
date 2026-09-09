<?php

declare(strict_types=1);

namespace Tests\Feature\VoicemailDigest;

use App\Jobs\SendVoicemailDigest as SendVoicemailDigestJob;
use App\Livewire\Utilities\VoicemailDigest as VoicemailDigestComponent;
use App\Models\Team;
use App\Models\User;
use App\Models\VoicemailDigest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

final class VoicemailDigestLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'personal_team' => false,
            'utility_voicemail_digest' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);

        $this->actingAs($this->user);
    }

    public function test_component_renders_successfully(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->assertSuccessful();
    }

    public function test_it_displays_schedules_for_current_team(): void
    {
        $digest1 = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Team Digest',
        ]);

        $otherTeam = Team::factory()->create();
        $digest2 = VoicemailDigest::factory()->create([
            'team_id' => $otherTeam->id,
            'name' => 'Other Team Digest',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->assertSee('Team Digest')
            ->assertDontSee('Other Team Digest');
    }

    public function test_it_paginates_schedules(): void
    {
        VoicemailDigest::factory()->count(30)->create([
            'team_id' => $this->team->id,
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            // All thirty belong to the team and are counted; the table pages at 25,
            // so which 25 land on page one is not asserted -- the factory stamps them
            // all in the same second, so that order is not deterministic.
            ->assertCountTableRecords(30)
            ->assertSet('tableRecordsPerPage', 25);
    }

    /*
     * Four cases covering modal open/close state (showCreateModal, showSendNowModal
     * and the state resets around them) are gone with the properties they asserted on.
     * Opening and closing a dialog is Filament's now; what those tests were really
     * protecting -- that a form opens with the right values -- is covered by the
     * prefill cases below.
     */

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->callAction('createDigest', [
                'name' => '',
                'recipients' => '',
                'subject' => '',
                'timezone' => '',
            ])
            ->assertHasFormErrors([
                'name',
                'recipients',
                'subject',
                'timezone',
            ]);
    }

    public function test_create_validates_schedule_type(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->callAction('createDigest', [
                'name' => 'Test Digest',
                'recipients' => 'test@example.com',
                'subject' => 'Subject',
                'schedule_type' => 'invalid',
                'timezone' => 'America/New_York',
            ])
            ->assertHasFormErrors(['schedule_type']);
    }

    public function test_create_successfully_creates_schedule(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->callAction('createDigest', [
                'name' => 'Test Digest',
                'client_number' => '1234',
                'billing_code' => '100',
                'recipients' => "test1@example.com\ntest2@example.com",
                'subject' => 'Daily Voicemail',
                'schedule_type' => 'daily',
                'schedule_time' => '09:00',
                'include_transcription' => true,
                'include_call_metadata' => false,
                'timezone' => 'America/Los_Angeles',
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertDatabaseHas('voicemail_digests', [
            'team_id' => $this->team->id,
            'name' => 'Test Digest',
            'client_number' => '1234',
            'billing_code' => '100',
            'subject' => 'Daily Voicemail',
            'schedule_type' => 'daily',
            'schedule_time' => '09:00',
            'include_transcription' => true,
            'include_call_metadata' => false,
            'enabled' => true,
            'timezone' => 'America/Los_Angeles',
        ]);

        $digest = VoicemailDigest::where('name', 'Test Digest')->first();
        $this->assertEquals(['test1@example.com', 'test2@example.com'], $digest->recipients);
    }

    public function test_create_handles_empty_optional_fields(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->callAction('createDigest', [
                'name' => 'Test Digest',
                'client_number' => '',
                'billing_code' => '',
                'recipients' => 'test@example.com',
                'subject' => 'Subject',
                'schedule_type' => 'daily',
                'timezone' => 'America/New_York',
            ])
            ->assertHasNoErrors();

        $digest = VoicemailDigest::where('name', 'Test Digest')->first();
        $this->assertNull($digest->client_number);
        $this->assertNull($digest->billing_code);
    }

    public function test_edit_loads_schedule_data(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Original Name',
            'client_number' => '5678',
            'billing_code' => '200',
            'recipients' => ['edit1@example.com', 'edit2@example.com'],
            'subject' => 'Edit Subject',
            'schedule_type' => 'weekly',
            'schedule_time' => '14:00',
            'schedule_day_of_week' => 3,
            'include_transcription' => false,
            'include_call_metadata' => true,
            'timezone' => 'America/Chicago',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->mountTableAction('edit', $digest)
            ->assertActionDataSet([
                'name' => 'Original Name',
                'client_number' => '5678',
                'billing_code' => '200',
                // Stored as an array; the textarea shows one address per line.
                'recipients' => "edit1@example.com\nedit2@example.com",
                'subject' => 'Edit Subject',
                'schedule_type' => 'weekly',
                'schedule_day_of_week' => 3,
                'include_transcription' => false,
                'include_call_metadata' => true,
                'timezone' => 'America/Chicago',
            ]);
    }

    public function test_update_validates_fields(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('edit', $digest, [
                'name' => '',
                'recipients' => '',
            ])
            ->assertHasFormErrors([
                'name',
                'recipients',
            ]);
    }

    public function test_update_successfully_updates_schedule(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Original',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('edit', $digest, [
                'name' => 'Updated Name',
                'client_number' => '9999',
                'recipients' => 'updated@example.com',
                'subject' => 'Updated Subject',
                'schedule_type' => 'monthly',
                'schedule_day_of_month' => 15,
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        $digest->refresh();
        $this->assertEquals('Updated Name', $digest->name);
        $this->assertEquals('9999', $digest->client_number);
        $this->assertEquals(['updated@example.com'], $digest->recipients);
        $this->assertEquals('Updated Subject', $digest->subject);
        $this->assertEquals('monthly', $digest->schedule_type);
        $this->assertEquals(15, $digest->schedule_day_of_month);
    }

    public function test_update_recalculates_next_run_at(): void
    {
        Carbon::setTestNow('2026-01-26 10:00:00');

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $this->team->id,
            'schedule_time' => '08:00',
            'next_run_at' => null,
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('edit', $digest, [
                'name' => $digest->name,
                'recipients' => implode("\n", $digest->recipients),
                'subject' => $digest->subject,
                'timezone' => $digest->timezone,
                'schedule_type' => 'daily',
                'schedule_time' => '15:00',
            ]);

        $digest->refresh();
        $this->assertNotNull($digest->next_run_at);

        Carbon::setTestNow();
    }

    public function test_delete_removes_schedule(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'To Delete',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('delete', $digest);

        $this->assertSoftDeleted('voicemail_digests', ['id' => $digest->id]);
    }

    public function test_toggle_enabled_disables_schedule(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('toggleEnabled', $digest);

        $digest->refresh();
        $this->assertFalse($digest->enabled);
    }

    public function test_toggle_enabled_enables_schedule_and_calculates_next_run(): void
    {
        Carbon::setTestNow('2026-01-26 10:00:00');

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $this->team->id,
            'enabled' => false,
            'next_run_at' => null,
            'schedule_time' => '15:00',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('toggleEnabled', $digest);

        $digest->refresh();
        $this->assertTrue($digest->enabled);
        $this->assertNotNull($digest->next_run_at);

        Carbon::setTestNow();
    }

    public function test_open_send_now_modal_prefills_date_range(): void
    {
        Carbon::setTestNow('2026-01-26 10:00:00');

        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $this->team->id,
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->mountTableAction('sendNow', $digest)
            ->assertActionDataSet(fn (array $data): bool => str_contains((string) $data['start_date'], '2026-01-25')
                && str_contains((string) $data['end_date'], '2026-01-26'));

        Carbon::setTestNow();
    }

    public function test_send_now_validates_dates(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('sendNow', $digest, ['start_date' => null, 'end_date' => null])
            ->assertHasFormErrors(['start_date', 'end_date']);
    }

    public function test_send_now_validates_end_date_after_start_date(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('sendNow', $digest, [
                'start_date' => '2026-01-26 10:00',
                'end_date' => '2026-01-25 10:00',
            ])
            ->assertHasFormErrors(['end_date']);
    }

    public function test_send_now_dispatches_job(): void
    {
        Queue::fake();

        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'timezone' => 'America/New_York',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('sendNow', $digest, [
                'start_date' => '2026-01-25 08:00',
                'end_date' => '2026-01-26 17:00',
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        Queue::assertPushed(SendVoicemailDigestJob::class, function ($job) use ($digest) {
            return $job->schedule->id === $digest->id
                && $job->startDate->format('Y-m-d H:i:s') === '2026-01-25 08:00:00'
                && $job->endDate->format('Y-m-d H:i:s') === '2026-01-26 17:00:00';
        });
    }

    public function test_get_timezones_returns_array(): void
    {
        $component = Livewire::test(VoicemailDigestComponent::class);

        $timezones = $component->instance()->getTimezones();

        $this->assertIsArray($timezones);
        $this->assertContains('America/New_York', $timezones);
        $this->assertContains('America/Los_Angeles', $timezones);
        $this->assertContains('UTC', $timezones);
    }

    public function test_get_schedule_types_returns_expected_values(): void
    {
        $component = Livewire::test(VoicemailDigestComponent::class);

        $types = $component->instance()->getScheduleTypes();

        $this->assertEquals([
            'immediate' => 'Immediate',
            'hourly' => 'Hourly',
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
        ], $types);
    }

    public function test_create_with_immediate_type_nulls_time_and_day_fields(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->callAction('createDigest', [
                'name' => 'Immediate Digest',
                'recipients' => 'test@example.com',
                'subject' => 'Immediate Subject',
                'schedule_type' => 'immediate',
                'schedule_time' => '08:00',
                'schedule_day_of_week' => 1,
                'schedule_day_of_month' => 15,
                'timezone' => 'America/New_York',
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        $digest = VoicemailDigest::where('name', 'Immediate Digest')->first();
        $this->assertEquals('immediate', $digest->schedule_type);
        $this->assertNull($digest->schedule_time);
        $this->assertNull($digest->schedule_day_of_week);
        $this->assertNull($digest->schedule_day_of_month);
    }

    public function test_update_with_immediate_type_nulls_time_and_day_fields(): void
    {
        $digest = VoicemailDigest::factory()->daily()->create([
            'team_id' => $this->team->id,
            'schedule_time' => '08:00',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->callTableAction('edit', $digest, [
                'schedule_type' => 'immediate',
            ])
            ->assertHasNoErrors()
            ->assertNotified();

        $digest->refresh();
        $this->assertEquals('immediate', $digest->schedule_type);
        $this->assertNull($digest->schedule_time);
        $this->assertNull($digest->schedule_day_of_week);
        $this->assertNull($digest->schedule_day_of_month);
    }

    public function test_get_days_of_week_returns_expected_values(): void
    {
        $component = Livewire::test(VoicemailDigestComponent::class);

        $days = $component->instance()->getDaysOfWeek();

        $this->assertEquals([
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ], $days);
    }
}
