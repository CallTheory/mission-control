<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\SyncMergeCommWebHooks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncISDataTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_dispatches_sync_merge_comm_web_hooks_job()
    {
        Queue::fake();

        $this->artisan('intelligent-data:sync')
            ->expectsOutput('')
            ->assertExitCode(0);

        Queue::assertPushed(SyncMergeCommWebHooks::class);
    }
}
