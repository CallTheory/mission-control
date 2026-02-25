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
            ->assertViewHas('schedules', function ($schedules) {
                return $schedules->count() === 25;
            });
    }

    public function test_open_create_modal_resets_state(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->set('state.name', 'Old Name')
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSet('state.name', '')
            ->assertSet('state.schedule_type', 'daily')
            ->assertSet('state.subject', 'Voicemail Digest')
            ->assertSet('state.timezone', 'America/New_York');
    }

    public function test_close_create_modal_resets_state(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->set('showCreateModal', true)
            ->set('state.name', 'Test')
            ->call('closeCreateModal')
            ->assertSet('showCreateModal', false)
            ->assertSet('state.name', '');
    }

    public function test_create_validates_required_fields(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->set('state.name', '')
            ->set('state.recipients', '')
            ->set('state.subject', '')
            ->set('state.timezone', '')
            ->call('create')
            ->assertHasErrors([
                'state.name' => 'required',
                'state.recipients' => 'required',
                'state.subject' => 'required',
                'state.timezone' => 'required',
            ]);
    }

    public function test_create_validates_schedule_type(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->set('state.name', 'Test Digest')
            ->set('state.recipients', 'test@example.com')
            ->set('state.subject', 'Subject')
            ->set('state.schedule_type', 'invalid')
            ->set('state.timezone', 'America/New_York')
            ->call('create')
            ->assertHasErrors(['state.schedule_type' => 'in']);
    }

    public function test_create_successfully_creates_schedule(): void
    {
        Livewire::test(VoicemailDigestComponent::class)
            ->set('state.name', 'Test Digest')
            ->set('state.client_number', '1234')
            ->set('state.billing_code', '100')
            ->set('state.recipients', "test1@example.com\ntest2@example.com")
            ->set('state.subject', 'Daily Voicemail')
            ->set('state.schedule_type', 'daily')
            ->set('state.schedule_time', '09:00')
            ->set('state.include_transcription', true)
            ->set('state.include_call_metadata', false)
            ->set('state.timezone', 'America/Los_Angeles')
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false)
            ->assertDispatched('saved');

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
            ->set('state.name', 'Test Digest')
            ->set('state.client_number', '')
            ->set('state.billing_code', '')
            ->set('state.recipients', 'test@example.com')
            ->set('state.subject', 'Subject')
            ->set('state.schedule_type', 'daily')
            ->set('state.timezone', 'America/New_York')
            ->call('create')
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
            ->call('edit', $digest)
            ->assertSet('editingRecord', $digest->id)
            ->assertSet('state.name', 'Original Name')
            ->assertSet('state.client_number', '5678')
            ->assertSet('state.billing_code', '200')
            ->assertSet('state.recipients', "edit1@example.com\nedit2@example.com")
            ->assertSet('state.subject', 'Edit Subject')
            ->assertSet('state.schedule_type', 'weekly')
            ->assertSet('state.schedule_time', '14:00')
            ->assertSet('state.schedule_day_of_week', 3)
            ->assertSet('state.include_transcription', false)
            ->assertSet('state.include_call_metadata', true)
            ->assertSet('state.timezone', 'America/Chicago');
    }

    public function test_close_edit_modal_resets_state(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->call('edit', $digest)
            ->assertSet('editingRecord', $digest->id)
            ->call('closeEditModal')
            ->assertSet('editingRecord', 0)
            ->assertSet('state', []);
    }

    public function test_update_validates_fields(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->call('edit', $digest)
            ->set('state.name', '')
            ->set('state.recipients', '')
            ->call('update', $digest)
            ->assertHasErrors([
                'state.name' => 'required',
                'state.recipients' => 'required',
            ]);
    }

    public function test_update_successfully_updates_schedule(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'name' => 'Original',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->call('edit', $digest)
            ->set('state.name', 'Updated Name')
            ->set('state.client_number', '9999')
            ->set('state.recipients', 'updated@example.com')
            ->set('state.subject', 'Updated Subject')
            ->set('state.schedule_type', 'monthly')
            ->set('state.schedule_day_of_month', 15)
            ->call('update', $digest)
            ->assertHasNoErrors()
            ->assertSet('editingRecord', 0)
            ->assertDispatched('saved');

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
            ->call('edit', $digest)
            ->set('state.schedule_type', 'daily')
            ->set('state.schedule_time', '15:00')
            ->call('update', $digest);

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
            ->call('delete', $digest)
            ->assertDispatched('saved');

        $this->assertSoftDeleted('voicemail_digests', ['id' => $digest->id]);
    }

    public function test_toggle_enabled_disables_schedule(): void
    {
        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'enabled' => true,
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->call('toggleEnabled', $digest)
            ->assertDispatched('saved');

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
            ->call('toggleEnabled', $digest)
            ->assertDispatched('saved');

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
            ->call('openSendNowModal', $digest->id)
            ->assertSet('showSendNowModal', true)
            ->assertSet('sendNowScheduleId', $digest->id)
            ->assertSet('sendNowState.start_date', function ($value) {
                return str_contains($value, '2026-01-25');
            })
            ->assertSet('sendNowState.end_date', function ($value) {
                return str_contains($value, '2026-01-26');
            });

        Carbon::setTestNow();
    }

    public function test_close_send_now_modal_resets_state(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->call('openSendNowModal', $digest->id)
            ->call('closeSendNowModal')
            ->assertSet('showSendNowModal', false)
            ->assertSet('sendNowScheduleId', 0)
            ->assertSet('sendNowState', []);
    }

    public function test_send_now_validates_dates(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->set('sendNowScheduleId', $digest->id)
            ->call('sendNow')
            ->assertHasErrors([
                'sendNowState.start_date' => 'required',
                'sendNowState.end_date' => 'required',
            ]);
    }

    public function test_send_now_validates_end_date_after_start_date(): void
    {
        $digest = VoicemailDigest::factory()->create(['team_id' => $this->team->id]);

        Livewire::test(VoicemailDigestComponent::class)
            ->set('sendNowScheduleId', $digest->id)
            ->set('sendNowState.start_date', '2026-01-26T10:00')
            ->set('sendNowState.end_date', '2026-01-25T10:00')
            ->call('sendNow')
            ->assertHasErrors(['sendNowState.end_date' => 'after']);
    }

    public function test_send_now_dispatches_job(): void
    {
        Queue::fake();

        $digest = VoicemailDigest::factory()->create([
            'team_id' => $this->team->id,
            'timezone' => 'America/New_York',
        ]);

        Livewire::test(VoicemailDigestComponent::class)
            ->set('sendNowScheduleId', $digest->id)
            ->set('sendNowState.start_date', '2026-01-25T08:00')
            ->set('sendNowState.end_date', '2026-01-26T17:00')
            ->call('sendNow')
            ->assertHasNoErrors()
            ->assertSet('showSendNowModal', false)
            ->assertDispatched('saved');

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
            ->set('state.name', 'Immediate Digest')
            ->set('state.recipients', 'test@example.com')
            ->set('state.subject', 'Immediate Subject')
            ->set('state.schedule_type', 'immediate')
            ->set('state.schedule_time', '08:00')
            ->set('state.schedule_day_of_week', 1)
            ->set('state.schedule_day_of_month', 15)
            ->set('state.timezone', 'America/New_York')
            ->call('create')
            ->assertHasNoErrors()
            ->assertDispatched('saved');

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
            ->call('edit', $digest)
            ->set('state.schedule_type', 'immediate')
            ->call('update', $digest)
            ->assertHasNoErrors()
            ->assertDispatched('saved');

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
