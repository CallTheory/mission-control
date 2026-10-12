<?php

namespace Tests\Feature\Console\Commands;

use Tests\TestCase;

/**
 * `recording:convert` joins an Intelligent Series call's WAV fragments with sox, caches the
 * result in Redis and queues transcription.
 *
 * These three tests never ran. They were written with `/** @test *\/`, which PHPUnit 12 no
 * longer recognises, so the class looked empty and the suite reported success while exiting
 * non-zero. Restoring the method names revealed that they could not have passed on any
 * machine, because they were written against an API that does not exist:
 *
 *   - `Redis::fake()` is not a Laravel feature. The facade forwards the call to Redis as a
 *     command literally named FAKE, and predis rejects it: "Command `FAKE` is not a
 *     registered Redis command." That alone aborted all three in setUp.
 *   - `Redis::assertExists()` / `assertMissing()` do not exist either, for the same reason.
 *   - `$this->assertDispatched()` / `assertNotDispatched()` are not TestCase methods. The
 *     real ones are Queue::assertPushed() and Bus::assertDispatched().
 *
 * And the premise was wrong even past those. The command does `new Call([...])` directly
 * rather than resolving it from the container, so `$this->mock(Call::class)` cannot
 * intercept it; `recordingData()` then queries the Intelligent Series database. Exercising
 * this command for real needs SQL Server, a Redis server and the `sox` binary — which makes
 * it an integration test, not a feature test.
 *
 * Skipped rather than deleted so the intent survives: the three behaviours below are worth
 * covering, and the bullet list above is what it would take. Doing it properly means either
 * moving them to tests/Integration (alongside the suite that already stands up SQL Server)
 * or making the command resolve Call through the container so the data source can be
 * substituted. Both are real changes and neither belongs in the commit that merely got
 * these files collected again.
 */
class ConvertRecordingTest extends TestCase
{
    private const WHY = 'Needs SQL Server, Redis and sox, and a command that resolves Call '
        .'through the container. See this class docblock.';

    public function test_it_converts_recording_and_dispatches_transcription_job(): void
    {
        $this->markTestSkipped(self::WHY);
    }

    public function test_it_handles_no_recordings(): void
    {
        $this->markTestSkipped(self::WHY);
    }

    public function test_it_cleans_up_files_after_conversion(): void
    {
        $this->markTestSkipped(self::WHY);
    }
}
