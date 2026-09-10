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
        $action = $component->instance()->processAction(production: true);

        $this->assertTrue($action->isConfirmationRequired());
        $this->assertSame('danger', $action->getColor());
        $this->assertStringContainsString('charges live cards', $action->getModalDescription());
    }

    public function test_the_test_run_says_no_real_money_moves(): void
    {
        $component = $this->withImport();
        $action = $component->instance()->processAction(production: false);

        $this->assertTrue($action->isConfirmationRequired());
        $this->assertStringContainsString('No real money moves', $action->getModalDescription());
    }

    public function test_neither_run_is_offered_with_nothing_imported(): void
    {
        $instance = Livewire::actingAs($this->user)->test(CardProcessing::class)->instance();

        $this->assertTrue($instance->processAction(production: false)->isDisabled());
        $this->assertTrue($instance->processAction(production: true)->isDisabled());
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

    public function test_clearing_the_import_empties_the_table(): void
    {
        $this->withImport()
            ->callAction('clear')
            ->assertNotified()
            ->assertSee('No import loaded');
    }
}
