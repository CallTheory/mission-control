<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class TracedTestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public bool $shouldFail = false) {}

    public function handle(): void
    {
        if ($this->shouldFail) {
            throw new RuntimeException('job blew up');
        }
    }
}
