<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Utilities\BetterEmails;
use App\Models\BetterEmails as BetterEmailsModel;
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

    public function test_edit_action_loads_the_record_into_the_modal(): void
    {
        $record = $this->config(['client_number' => '7777']);

        Livewire::test(BetterEmails::class)
            ->call('editBetterEmail', $record->id)
            ->assertSet('editingRecord', $record->id)
            ->assertSet('state.client_number', '7777');
    }

    public function test_delete_action_removes_the_record(): void
    {
        $record = $this->config();

        Livewire::test(BetterEmails::class)
            ->call('deleteBetterEmail', $record->id);

        $this->assertDatabaseMissing('better_emails', ['id' => $record->id]);
    }
}
