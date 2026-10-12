<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\SyncMergeCommWebHooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncISDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * This test used to assert that the command dispatches SyncMergeCommWebHooks. It never
     *
     * ran -- it was discovered by PHPUnit 12 dropping `/** @test *\/` -- and the assertion
     * had stopped being true at some point before the initial public commit, because the
     * dispatch in App\Console\Commands\SyncISData::handle() is commented out:
     *
     *     // SyncMergeCommWebHooks::dispatch();
     *
     * So the command is a no-op that returns SUCCESS, and it is still scheduled every
     * thirty minutes in App\Console\Kernel. Asserting the dispatch again would mean
     * uncommenting that line, which is a decision about whether the sync should run at all
     * -- not something a test should force.
     *
     * This asserts what the command actually does, so that re-enabling the dispatch fails
     * here and whoever does it has to come and restore the real assertion.
     */
    public function test_it_succeeds_without_dispatching_anything(): void
    {
        Queue::fake();

        $this->artisan('intelligent-data:sync')->assertExitCode(0);

        Queue::assertNotPushed(SyncMergeCommWebHooks::class);
    }
}
