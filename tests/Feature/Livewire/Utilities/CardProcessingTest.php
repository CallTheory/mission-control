<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Utilities;

use App\Livewire\Utilities\CardProcessing;
use App\Models\DataSource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Card processing charges real cards, and had no tests at all.
 *
 * The Stripe calls cannot run here, so what is covered is everything around them:
 * that the live run is guarded, that the guard is a modal saying what it does rather
 * than the browser's confirm() the buttons used to rely on, that neither run is
 * offered with nothing loaded, and that the imported rows reach the table.
 */
class CardProcessingTest extends TestCase
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

    private function withImport(): Testable
    {
        return Livewire::actingAs($this->user)
            ->test(CardProcessing::class)
            ->set('headers', ['Client', 'Name', 'Account', 'D', 'E', 'PaymentID', 'G', 'Amount'])
            ->set('records', [
                ['1001', 'Northside Medical', 'ACC-1', '', '', 'pm_abc', '', '$120.00'],
                ['1002', 'Harbour Dental', 'ACC-2', '', '', 'pm_def', '', '$80.00'],
            ]);
    }

    public function test_the_live_run_requires_confirmation_and_says_what_it_does(): void
    {
        $component = $this->withImport();
        $action = $component->instance()->processLiveAction();

        $this->assertTrue($action->isConfirmationRequired());
        $this->assertSame('danger', $action->getColor());
        $this->assertStringContainsString('charges live cards', $action->getModalDescription());
    }

    public function test_the_test_run_says_no_real_money_moves(): void
    {
        $component = $this->withImport();
        $action = $component->instance()->processTestAction();

        $this->assertTrue($action->isConfirmationRequired());
        $this->assertStringContainsString('No real money moves', $action->getModalDescription());
    }

    public function test_neither_run_is_offered_with_nothing_imported(): void
    {
        $instance = Livewire::actingAs($this->user)->test(CardProcessing::class)->instance();

        $this->assertTrue($instance->processTestAction()->isDisabled());
        $this->assertTrue($instance->processLiveAction()->isDisabled());
    }

    public function test_imported_rows_reach_the_table(): void
    {
        $this->withImport()
            ->assertSee('Northside Medical')
            ->assertSee('Harbour Dental')
            ->assertSee('pm_abc');
    }

    public function test_rows_start_as_pending(): void
    {
        $this->withImport()->assertSee('Pending');
    }

    public function test_a_charged_row_is_marked_as_such(): void
    {
        $this->withImport()
            ->set('processResults', ['charges' => ['pm_abc' => []], 'failures' => []])
            ->assertSee('Charged');
    }

    public function test_a_failed_row_is_marked_as_such(): void
    {
        $this->withImport()
            ->set('processResults', ['charges' => [], 'failures' => ['pm_def' => ['results' => 'card_declined']]])
            ->assertSee('Failed');
    }

    /**
     * The buttons rendered and clicked into nothing. Filament re-resolves a clicked
     * action by name -- `{name}Action()` or `{name}()` on the component -- and the two
     * runs were built by one parameterised builder, processAction(bool), named processTest and
     * processLive, with no method of either name. resolveAction() returned null,
     * mountAction() unmounted quietly, and nothing ran or logged. The tests above
     * called the builder directly, which is why they never noticed.
     *
     * These go through the click path. A $0.00 row takes the whole batch loop without
     * a Stripe call (only a positive amount is charged), so no network is needed.
     */
    private function withZeroAmountImport(): Testable
    {
        return Livewire::actingAs($this->user)
            ->test(CardProcessing::class)
            ->set('headers', ['Client', 'Name', 'Account', 'D', 'E', 'PaymentID', 'G', 'Amount'])
            ->set('records', [
                1 => ['1001', 'Northside Medical', 'ACC-1', '', '', 'cus_abc', '', '$0.00', 'processed' => 'no'],
                2 => ['1002', 'Harbour Dental', 'ACC-2', '', '', 'cus_def', '', '$0.00', 'processed' => 'no'],
            ]);
    }

    public function test_both_runs_resolve_when_clicked(): void
    {
        $this->withZeroAmountImport()
            ->assertActionExists('processTest')
            ->assertActionExists('processLive');
    }

    public function test_clicking_the_test_run_processes_the_batch(): void
    {
        $component = $this->withZeroAmountImport()
            ->callAction('processTest')
            ->assertNotified('0 charged, 0 failed');

        foreach ($component->get('records') as $record) {
            $this->assertSame('yes', $record['processed']);
        }
    }

    public function test_clicking_the_live_run_without_a_live_key_says_so(): void
    {
        // setUp() configures only a test key. The old guard here returned
        // response()->withErrors(), which is not a method on the response factory.
        $component = $this->withZeroAmountImport()
            ->callAction('processLive')
            ->assertNotified('Missing live Stripe key');

        foreach ($component->get('records') as $record) {
            $this->assertSame('no', $record['processed'], 'Rows were marked processed with no key to charge them.');
        }
    }

    public function test_clearing_the_import_empties_the_table(): void
    {
        $this->withImport()
            ->callAction('clear')
            ->assertNotified()
            ->assertSee('No import loaded');
    }
}
