<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Utilities;

use App\Livewire\Utilities\CardProcessing;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The upload itself, which nothing covered: every other test here sets `records` directly,
 * so the one step a user actually performs was never exercised.
 */
class CardProcessingUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        DataSource::create(['stripe_test_secret_key' => 'sk_test_x']);

        $this->user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false, 'utility_card_processing' => true]);
        $this->user->teams()->attach($team, ['role' => 'admin']);
        $this->user->switchTeam($team);
        $this->user = $this->user->fresh();
    }

    /**
     * A TBS export, in the shape the parser is written for.
     *
     * Data rows carry 38 columns with the billing detail at 25-32. The header row is
     * narrower -- the parser reads its labels from 17-24, eight columns to the left -- which
     * is why the report header line falls under the 38-column test and is not mistaken for a
     * row to charge.
     */
    private function tbsCsv(): string
    {
        $header = array_map(fn (int $i): string => "col{$i}", range(0, 32));
        $header[17] = 'Client';
        $header[18] = 'Name';
        $header[19] = 'Account';
        $header[22] = 'Stripe Customer';
        $header[24] = 'Amount';

        $row = function (string $client, string $name, string $account, string $customer, string $amount): string {
            $cells = array_fill(0, 38, '');
            $cells[25] = $client;
            $cells[26] = $name;
            $cells[27] = $account;
            $cells[30] = $customer;
            $cells[32] = $amount;

            return implode(',', $cells);
        };

        return implode("\r\n", [
            implode(',', $header),
            $row('1001', 'Northside Medical', 'ACC-1', 'cus_abc', '120.00'),
            $row('1002', 'Harbour Dental', 'ACC-2', 'cus_def', '80.00'),
        ]);
    }

    private function upload(?string $csv = null, string $name = 'tbs-export.csv'): Testable
    {
        return Livewire::actingAs($this->user)
            ->test(CardProcessing::class)
            ->callAction('upload', data: [
                'tbsExportFile' => UploadedFile::fake()->createWithContent($name, $csv ?? $this->tbsCsv()),
            ]);
    }

    /**
     * The regression this file was written for.
     *
     * Filament's FileUpload stores the upload during form submission and leaves a path
     * string in the action's data. save() needs the uploaded file, so the `file` rule threw
     * a ValidationException into an error bag nothing on this page renders: the upload
     * appeared to work, a file with a generated name turned up on disk, and no row was ever
     * imported -- with nothing in the console and nothing in GlitchTip, because a
     * ValidationException is a handled exception and is not reported.
     */
    public function test_uploading_a_tbs_export_imports_its_rows(): void
    {
        $component = $this->upload()->assertHasNoActionErrors();

        $this->assertCount(2, $component->get('records'));
    }

    public function test_the_imported_rows_carry_the_billing_columns(): void
    {
        $records = $this->upload()->get('records');
        $first = array_values($records)[0];

        $this->assertSame('1001', $first[0]);
        $this->assertSame('Northside Medical', $first[1]);
        $this->assertSame('ACC-1', $first[2]);
        $this->assertSame('cus_abc', $first[5]);
        $this->assertSame('120.00', $first[7]);
        $this->assertSame('no', $first['processed']);
    }

    public function test_the_imported_rows_reach_the_table(): void
    {
        $this->upload()
            ->assertSee('Northside Medical')
            ->assertSee('Harbour Dental')
            ->assertSee('cus_abc');
    }

    public function test_the_column_labels_come_from_the_header_row(): void
    {
        $this->assertSame(
            ['Client', 'Name', 'Account', 'col20', 'col21', 'Stripe Customer', 'col23', 'Amount'],
            $this->upload()->get('headers')
        );
    }

    /**
     * Where the file lands matters: the export step and the reload path both look for it
     * under card-processing/ on the local disk, and the name has to be server-chosen rather
     * than the one the browser supplied.
     */
    public function test_the_upload_is_stored_under_card_processing(): void
    {
        $path = $this->upload()->get('file_path');

        $this->assertStringStartsWith('card-processing/', $path);
        $this->assertStringEndsNotWith('tbs-export.csv', $path);
        Storage::disk('local')->assertExists($path);
    }

    /** A reload has to come back to the same import, which is what the session copy is for. */
    public function test_the_import_survives_a_reload(): void
    {
        $records = $this->upload()->get('records');

        $this->assertSame(
            $records,
            Livewire::actingAs($this->user)->test(CardProcessing::class)->get('records')
        );
    }

    /**
     * The rows are read by column position, so a report rebuilt to a different width yields
     * nothing. An empty table reads as "no balances to bill", which is the wrong conclusion.
     */
    public function test_a_file_that_parses_to_nothing_says_so(): void
    {
        $narrow = implode("\r\n", [
            implode(',', array_fill(0, 33, 'header')),
            implode(',', array_fill(0, 12, 'value')),
        ]);

        $this->upload($narrow)
            ->assertNotified('Nothing to import')
            ->assertSee('No import loaded');
    }

    /**
     * Clearing an import used to call session()->flush(), which empties the whole session --
     * authentication included -- so the next request bounced the user to the login screen.
     */
    public function test_clearing_an_import_does_not_log_the_user_out(): void
    {
        session()->put('a-key-belonging-to-something-else', 'kept');

        $this->upload()->callAction('clear')->assertSee('No import loaded');

        $this->assertTrue(auth()->check(), 'Clearing the import signed the user out.');
        $this->assertSame('kept', session()->get('a-key-belonging-to-something-else'));
    }

    public function test_clearing_an_import_forgets_the_import(): void
    {
        $this->upload()->callAction('clear');

        $this->assertNull(session()->get('utilities.card-processing.import_records'));
        $this->assertNull(session()->get('utilities.card-processing.import_file'));

        $this->assertNull(
            Livewire::actingAs($this->user)->test(CardProcessing::class)->get('records')
        );
    }

    /**
     * The header row is read as labels, so it must never also arrive as a row to charge --
     * which it would if a report's header spanned the full width of its data rows.
     */
    public function test_a_full_width_header_row_is_not_charged(): void
    {
        $wide = function (string $client): string {
            $cells = array_fill(0, 38, '');
            $cells[25] = $client;
            $cells[26] = 'Name';
            $cells[27] = 'ACC';
            $cells[30] = 'cus_x';
            $cells[32] = '1.00';

            return implode(',', $cells);
        };

        $csv = implode("\r\n", [$wide('HEADER'), $wide('1001')]);

        $records = $this->upload($csv)->get('records');

        $this->assertCount(1, $records);
        $this->assertSame('1001', array_values($records)[0][0]);
    }
}
