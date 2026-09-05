<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesBoardComponent;
use App\Livewire\Utilities\BoardApproveMessage;
use App\Livewire\Utilities\BoardCheck;
use App\Livewire\Utilities\BoardConfirmProblem;
use App\Livewire\Utilities\BoardDispatcherReviewMessage;
use App\Livewire\Utilities\BoardFlagIssue;
use App\Livewire\Utilities\BoardMessageOk;
use App\Livewire\Utilities\BoardReport;
use App\Livewire\Utilities\BoardReview;
use App\Livewire\Utilities\BoardSupervisorReviewMessage;
use App\Models\BoardCheckItem;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LivewireUI\Modal\ModalComponent;
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
    // The board modals write board check items, so they must gate themselves:
    // Livewire does not re-apply the page controller's authorize() on
    // POST /livewire/update, and four of them are linked from no page at all.
    // ------------------------------------------------------------------

    /**
     * @return array<string, array{class-string, Capability}>
     */
    public static function boardModals(): array
    {
        return [
            'approve (dispatcher)' => [BoardApproveMessage::class, Capability::UtilityBoardCheck],
            'flag issue (dispatcher)' => [BoardFlagIssue::class, Capability::UtilityBoardCheck],
            'dispatcher review' => [BoardDispatcherReviewMessage::class, Capability::UtilityBoardCheck],
            'confirm problem (supervisor)' => [BoardConfirmProblem::class, Capability::BoardReview],
            'message ok (supervisor)' => [BoardMessageOk::class, Capability::BoardReview],
            'supervisor review' => [BoardSupervisorReviewMessage::class, Capability::BoardReview],
        ];
    }

    #[DataProvider('boardModals')]
    public function test_board_modals_deny_a_user_without_the_capability(string $component, Capability $capability): void
    {
        $item = $this->item();
        $denied = $this->createUserWithout($this->team, 'admin', $capability);

        Livewire::actingAs($denied)
            ->test($component, ['msgId' => $item->msgId])
            ->assertForbidden();
    }

    #[DataProvider('boardModals')]
    public function test_board_modals_allow_a_user_holding_the_capability(string $component, Capability $capability): void
    {
        $item = $this->item();
        $allowed = $this->createUserWithRole($this->team, 'admin');

        Livewire::actingAs($allowed)
            ->test($component, ['msgId' => $item->msgId])
            ->assertOk();
    }

    /**
     * The regression guard that matters: a board modal added later without a
     * capability declaration fails here rather than shipping as an open write.
     */
    public function test_every_board_modal_declares_a_capability(): void
    {
        $missing = [];

        foreach (glob(app_path('Livewire/Utilities/Board*.php')) as $path) {
            $class = 'App\\Livewire\\Utilities\\'.basename($path, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, ModalComponent::class)) {
                continue;
            }

            if (! in_array(AuthorizesBoardComponent::class, class_uses_recursive($class), true)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'Board modals missing AuthorizesBoardComponent: '.implode(', ', $missing));
    }
}
