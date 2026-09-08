<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\System;

use App\Enums\Capability;
use App\Livewire\System\BoardCheck;
use App\Livewire\System\FaxNotificationSettings;
use App\Livewire\System\Timezone;
use App\Models\DataSource;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;

/**
 * The System settings panels that write a single row, after moving to Filament
 * schemas. All three previously loaded their row with firstOrFail() in mount(),
 * which fataled outright on an installation that had not written one yet.
 */
class SystemSettingsPanelsTest extends TestCase
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

    // ------------------------------------------------------------------
    // Switch timezone
    // ------------------------------------------------------------------

    public function test_timezone_saves(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Timezone::class)
            ->fillForm(['switch_data_timezone' => 'America/New_York'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('America/New_York', Settings::first()->switch_data_timezone);
    }

    public function test_timezone_rejects_a_value_that_is_not_a_timezone(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Timezone::class)
            ->fillForm(['switch_data_timezone' => 'Mars/Olympus_Mons'])
            ->call('save')
            ->assertHasFormErrors(['switch_data_timezone']);
    }

    // ------------------------------------------------------------------
    // Board check
    // ------------------------------------------------------------------

    public function test_board_check_saves(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BoardCheck::class)
            ->fillForm([
                'board_check_starting_msgId' => '4242',
                'board_check_people_praise_export_method' => 'api',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = Settings::first();

        $this->assertSame(4242, (int) $settings->board_check_starting_msgId);
        $this->assertSame('api', $settings->board_check_people_praise_export_method);
    }

    public function test_board_check_reloads_what_was_saved(): void
    {
        Livewire::actingAs($this->admin())
            ->test(BoardCheck::class)
            ->fillForm([
                'board_check_starting_msgId' => '99',
                'board_check_people_praise_export_method' => 'file',
            ])
            ->call('save');

        Livewire::actingAs($this->admin())
            ->test(BoardCheck::class)
            ->assertFormSet(['board_check_people_praise_export_method' => 'file']);
    }

    // ------------------------------------------------------------------
    // Fax notifications
    // ------------------------------------------------------------------

    public function test_fax_notification_addresses_save(): void
    {
        Livewire::actingAs($this->admin())
            ->test(FaxNotificationSettings::class)
            ->fillForm([
                'fax_buildup_notification_email' => 'queue@example.test',
                'fax_failure_notification_email' => 'failures@example.test',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $datasource = DataSource::first();

        $this->assertSame('queue@example.test', $datasource->fax_buildup_notification_email);
        $this->assertSame('failures@example.test', $datasource->fax_failure_notification_email);
    }

    public function test_fax_notification_rejects_a_malformed_address(): void
    {
        Livewire::actingAs($this->admin())
            ->test(FaxNotificationSettings::class)
            ->fillForm(['fax_buildup_notification_email' => 'not-an-address'])
            ->call('save')
            ->assertHasFormErrors(['fax_buildup_notification_email']);
    }

    // ------------------------------------------------------------------
    // The crash these three used to share
    // ------------------------------------------------------------------

    /**
     * Settings::firstOrFail() and DataSource::firstOrFail() in mount() threw on a
     * fresh installation. Each panel now creates the row it edits.
     */
    public function test_each_panel_renders_with_no_row_written_yet(): void
    {
        $this->assertNull(Settings::first());
        $this->assertNull(DataSource::first());

        $admin = $this->admin();

        foreach ([Timezone::class, BoardCheck::class, FaxNotificationSettings::class] as $component) {
            Livewire::actingAs($admin)->test($component)->assertOk();
        }
    }

    public function test_a_user_without_system_access_cannot_save(): void
    {
        Settings::create(['switch_data_timezone' => 'UTC']);

        $denied = $this->createUserWithout($this->team, 'admin', Capability::SystemAccess);

        try {
            Livewire::actingAs($denied)
                ->test(Timezone::class)
                ->fillForm(['switch_data_timezone' => 'America/Chicago'])
                ->call('save');
        } catch (\Throwable) {
            // Livewire renders the authorization failure; the property under test is
            // that nothing was written.
        }

        $this->assertSame('UTC', Settings::first()->switch_data_timezone);
    }
}
