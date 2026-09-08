<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesBoardComponent;
use App\Livewire\Utilities\BoardActivity;
use App\Livewire\Utilities\BoardCheck;
use App\Livewire\Utilities\BoardReport;
use App\Livewire\Utilities\BoardReview;
use App\Models\BoardCheckItem;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Traits\CreatesTeamUsers;
use Tests\Traits\InteractsWithFeatureFlags;

class BoardTablesTest extends TestCase
{
    use CreatesTeamUsers;
    use InteractsWithFeatureFlags;
    use RefreshDatabase;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = $this->createSeededTeam();

        // Utility capabilities need the system feature flag AND the team flag on top
        // of the role capability, so a seeded admin does not hold utility.board_check
        // until both are enabled.
        $this->enableSystemFeature('board-check');
        $this->team->forceFill(['utility_board_check' => true])->save();
    }

    private function item(array $attributes = []): BoardCheckItem
    {
        $record = new BoardCheckItem;
        $record->forceFill(array_merge([
            'msgId' => random_int(1000, 9999),
            'callId' => random_int(1000, 9999),
        ], $attributes))->save();

        return $record->refresh();
    }

    // ------------------------------------------------------------------
    // Each listing shows its own stage of the workflow, and only that stage.
    // ------------------------------------------------------------------

    public function test_board_check_lists_only_unreviewed_items(): void
    {
        $unreviewed = $this->item();
        $approved = $this->item(['approved_at' => now()]);
        $flagged = $this->item(['problem_found_at' => now()]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(BoardCheck::class)
            ->assertCanSeeTableRecords([$unreviewed])
            ->assertCanNotSeeTableRecords([$approved, $flagged]);
    }

    public function test_board_review_lists_items_awaiting_a_supervisor(): void
    {
        $awaiting = $this->item(['approved_at' => now()]);
        $flagged = $this->item(['problem_found_at' => now()]);
        $unreviewed = $this->item();
        $resolved = $this->item(['approved_at' => now(), 'marked_ok_at' => now()]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(BoardReview::class)
            ->assertCanSeeTableRecords([$awaiting, $flagged])
            ->assertCanNotSeeTableRecords([$unreviewed, $resolved]);
    }

    public function test_board_report_lists_only_resolved_items(): void
    {
        $ok = $this->item(['approved_at' => now(), 'marked_ok_at' => now()]);
        $verified = $this->item(['problem_found_at' => now(), 'problem_verified_at' => now()]);
        $pending = $this->item(['approved_at' => now()]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(BoardReport::class)
            ->assertCanSeeTableRecords([$ok, $verified])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_board_report_distinguishes_approved_from_problem_verified(): void
    {
        $this->item(['approved_at' => now(), 'marked_ok_at' => now()]);

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(BoardReport::class)
            ->assertSee('Approved');
    }

    // ------------------------------------------------------------------
    // The review dialog is now a Filament action on the listing, so the listing
    // is what has to gate it. Livewire does not re-apply the page controller's
    // authorize() on POST /livewire/update, and these listings previously had no
    // component-level guard at all.
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{class-string, Capability}>
     */
    public static function boardListings(): array
    {
        return [
            'board check' => [BoardCheck::class, Capability::UtilityBoardCheck],
            'board review' => [BoardReview::class, Capability::BoardReview],
            'board report' => [BoardReport::class, Capability::BoardReport],
            'board activity' => [BoardActivity::class, Capability::BoardActivity],
        ];
    }

    #[DataProvider('boardListings')]
    public function test_board_listings_deny_a_user_without_the_capability(string $component, Capability $capability): void
    {
        $denied = $this->createUserWithout($this->team, 'admin', $capability);

        Livewire::actingAs($denied)
            ->test($component)
            ->assertForbidden();
    }

    #[DataProvider('boardListings')]
    public function test_board_listings_allow_a_user_holding_the_capability(string $component, Capability $capability): void
    {
        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test($component)
            ->assertOk();
    }

    public function test_every_board_listing_declares_a_capability(): void
    {
        $missing = [];

        foreach (glob(app_path('Livewire/Utilities/Board*.php')) as $path) {
            $class = 'App\\Livewire\\Utilities\\'.basename($path, '.php');

            if (! class_exists($class)) {
                continue;
            }

            if (! in_array(AuthorizesBoardComponent::class, class_uses_recursive($class), true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'Board components missing AuthorizesBoardComponent: '.implode(', ', $missing));
    }

    // ------------------------------------------------------------------
    // The review action itself.
    // ------------------------------------------------------------------

    public function test_confirming_a_message_approves_it(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(BoardCheck::class)
            ->callTableAction('review', $item);

        $item->refresh();

        $this->assertNotNull($item->approved_at);
        $this->assertNotNull($item->marked_ok_at);
        $this->assertNull($item->problem_found_at);
    }

    public function test_a_denied_user_cannot_confirm_a_message(): void
    {
        $item = $this->item();
        $denied = $this->createUserWithout($this->team, 'admin', Capability::UtilityBoardCheck);

        try {
            Livewire::actingAs($denied)
                ->test(BoardCheck::class)
                ->callTableAction('review', $item);
        } catch (\Throwable) {
            // Livewire renders the authorization failure; the property under test is
            // that nothing was written.
        }

        $this->assertNull($item->refresh()->approved_at);
    }

    public function test_the_review_action_records_the_outcome_in_the_activity_log(): void
    {
        $item = $this->item();

        Livewire::actingAs($this->createUserWithRole($this->team, 'admin'))
            ->test(BoardCheck::class)
            ->callTableAction('review', $item);

        $this->assertDatabaseHas('activities', [
            'msgId' => $item->msgId,
            'activity_type' => 'Dispatcher Approved',
        ]);
    }
}
