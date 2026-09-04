<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;
use Tests\Traits\CapturesSpans;

class ConsoleTracingTest extends TestCase
{
    use CapturesSpans;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCapturesSpans();
        parent::tearDown();
    }

    public function test_a_command_produces_a_span(): void
    {
        $this->enableTracing();

        $this->fireCommand('inspire', 0);

        $span = $this->spanNamed('artisan inspire');

        $this->assertNotNull($span);
        $this->assertSame(0, $span->getAttributes()->toArray()['laravel.command.exit_code']);
    }

    public function test_a_failing_command_marks_the_span_as_errored(): void
    {
        $this->enableTracing();

        $this->fireCommand('inspire', 1);

        $this->assertSame('Error', $this->spanNamed('artisan inspire')->getStatus()->getCode());
    }

    public function test_long_running_workers_are_ignored(): void
    {
        // The bug this prevents: `artisan horizon` runs for days, so its span
        // would never end, never export, and would become the active parent of
        // every job the worker processes.
        $this->enableTracing();

        foreach (['horizon', 'queue:work', 'horizon:supervisor', 'schedule:work'] as $command) {
            $this->fireCommand($command, 0);
        }

        $this->assertSame([], $this->spans());
    }

    public function test_a_scheduled_task_produces_a_span(): void
    {
        $this->enableTracing();

        // Build a real scheduled Event through the scheduler rather than
        // constructing one by hand (its mutex is not container-resolvable).
        $task = app(Schedule::class)
            ->command('inspire')
            ->everyMinute();

        Event::dispatch(new ScheduledTaskStarting($task));
        Event::dispatch(new ScheduledTaskFinished($task, 0.25));

        $span = collect($this->spans())->first(
            fn ($s) => str_starts_with($s->getName(), 'schedule ')
        );

        $this->assertNotNull($span);
        $this->assertSame(250, $span->getAttributes()->toArray()['laravel.schedule.runtime_ms']);
    }

    private function fireCommand(string $command, int $exitCode): void
    {
        Event::dispatch(new CommandStarting($command, new ArrayInput([]), new NullOutput));
        Event::dispatch(new CommandFinished($command, new ArrayInput([]), new NullOutput, $exitCode));
    }
}
