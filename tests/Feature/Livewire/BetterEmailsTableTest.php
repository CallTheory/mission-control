<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Utilities\BetterEmails;
use App\Models\BetterEmails as BetterEmailsModel;
use App\Models\System\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Covers the Better Emails list after its move to a Filament table. The screen had
 * no coverage at all before the conversion, so these also pin down behaviour the
 * old markup only implied — notably that the drop location is derived, not stored.
 */
class BetterEmailsTableTest extends TestCase
{
    use RefreshDatabase;

    private function config(array $attributes = []): BetterEmailsModel
    {
        // The model is fully guarded, so build it attribute by attribute rather than
        // loosening its mass-assignment protection for the sake of a test.
        $record = new BetterEmailsModel;
        $record->forceFill(array_merge([
            'client_number' => '1001',
            'title' => 'Daily Summary',
            'description' => 'Messages taken in the last 24 hours',
            'recipients' => json_encode(['ops@example.test']),
            'report_metadata' => true,
            'message_history' => true,
            'theme' => 'standard',
            'subject' => 'Your daily summary',
            'logo' => 'https://example.test/logo.png',
            'logo_alt' => 'Example',
            'logo_link' => 'https://example.test',
            'button_text' => 'View',
            'button_link' => 'https://example.test/portal',
        ], $attributes))->save();

        return $record->refresh();
    }

    public function test_it_lists_configurations(): void
    {
        $first = $this->config(['client_number' => '1001']);
        $second = $this->config(['client_number' => '2002', 'subject' => 'Weekly roundup']);

        Livewire::test(BetterEmails::class)
            ->assertCanSeeTableRecords([$first, $second])
            ->assertCanRenderTableColumn('client_number')
            ->assertCanRenderTableColumn('subject')
            ->assertCanRenderTableColumn('recipients')
            ->assertCanRenderTableColumn('drop_location');
    }

    public function test_it_searches_by_client_number_and_subject(): void
    {
        $summary = $this->config(['client_number' => '1001', 'subject' => 'Your daily summary']);
        $roundup = $this->config(['client_number' => '2002', 'subject' => 'Weekly roundup']);

        Livewire::test(BetterEmails::class)
            ->searchTable('2002')
            ->assertCanSeeTableRecords([$roundup])
            ->assertCanNotSeeTableRecords([$summary])
            ->searchTable('daily summary')
            ->assertCanSeeTableRecords([$summary])
            ->assertCanNotSeeTableRecords([$roundup]);
    }

    public function test_recipients_are_decoded_from_json(): void
    {
        $this->config(['recipients' => json_encode(['ops@example.test', 'dispatch@example.test'])]);

        Livewire::test(BetterEmails::class)
            ->assertSee('ops@example.test')
            ->assertSee('dispatch@example.test');
    }

    public function test_drop_location_is_derived_from_the_configured_unc_path(): void
    {
        config(['app.unc_path' => '\\\\fileserver\\share']);
        $record = $this->config(['client_number' => '4242']);

        Livewire::test(BetterEmails::class)
            ->assertSee('\\\\fileserver\\share\\better-emails\\4242\\'.$record->id, escape: false);
    }

    public function test_edit_action_loads_the_record_into_the_dialog(): void
    {
        $record = $this->config([
            'client_number' => '7777',
            'recipients' => json_encode(['ops@example.test', 'dispatch@example.test']),
        ]);

        Livewire::test(BetterEmails::class)
            ->mountTableAction('edit', $record)
            ->assertActionDataSet([
                'client_number' => '7777',
                // Stored as JSON; the textarea renders it as one address per line.
                'recipients' => "ops@example.test\ndispatch@example.test",
            ]);
    }

    public function test_creating_a_configuration_prefills_the_system_defaults(): void
    {
        // better_emails_* are not in the model's $fillable, so build the row directly.
        $settings = new Settings;
        $settings->forceFill([
            'better_emails_title' => 'Default Title',
            'better_emails_subject' => 'Default Subject',
        ])->save();

        Livewire::test(BetterEmails::class)
            ->mountAction('createConfiguration')
            ->assertActionDataSet([
                'title' => 'Default Title',
                'subject' => 'Default Subject',
            ]);
    }

    public function test_a_created_configuration_stores_recipients_as_json(): void
    {
        Livewire::test(BetterEmails::class)
            ->callAction('createConfiguration', [
                'client_number' => '3003',
                'subject' => 'Nightly',
                'title' => 'Nightly Summary',
                'description' => 'Overnight messages',
                'recipients' => "ops@example.test\ndispatch@example.test",
                'report_metadata' => true,
                'message_history' => true,
                'theme' => 'standard',
                'logo' => 'https://example.test/logo.png',
                'logo_alt' => 'Example',
                'logo_link' => 'https://example.test',
                'button_text' => 'View',
                'button_link' => 'https://example.test/portal',
            ])
            ->assertHasNoErrors();

        $record = BetterEmailsModel::where('client_number', '3003')->firstOrFail();

        $this->assertSame(
            ['ops@example.test', 'dispatch@example.test'],
            json_decode($record->recipients, true)
        );
    }

    public function test_delete_action_removes_the_record(): void
    {
        $record = $this->config();

        Livewire::test(BetterEmails::class)
            ->call('deleteBetterEmail', $record->id);

        $this->assertDatabaseMissing('better_emails', ['id' => $record->id]);
    }
}
