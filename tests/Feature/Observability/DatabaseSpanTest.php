<?php

declare(strict_types=1);

namespace Tests\Feature\Observability;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\CapturesSpans;

class DatabaseSpanTest extends TestCase
{
    use CapturesSpans;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCapturesSpans();
        parent::tearDown();
    }

    public function test_no_db_spans_when_the_sub_toggle_is_off(): void
    {
        $this->enableTracing();

        Route::get('/trace-db', function () {
            DB::table('users')->count();

            return response('ok');
        });

        $this->get('/trace-db');

        $dbSpans = collect($this->spans())->filter(
            fn ($s) => str_starts_with($s->getName(), 'SELECT')
        );

        $this->assertCount(0, $dbSpans, 'db spans are off by default');
    }

    public function test_db_spans_are_recorded_when_enabled(): void
    {
        $this->enableTracing(['observability.tracing.instrumentation.db.enabled' => true]);

        DB::table('users')->count();

        $span = collect($this->spans())->first(
            fn ($s) => str_starts_with($s->getName(), 'SELECT')
        );

        $this->assertNotNull($span);

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('sqlite', $attributes['db.system']);
        $this->assertSame('SELECT', $attributes['db.operation.name']);
    }

    public function test_query_literals_are_sanitized_out_of_the_statement(): void
    {
        $this->enableTracing(['observability.tracing.instrumentation.db.enabled' => true]);

        DB::select("select * from users where name = 'Jane Patient' and id = 4242");

        $span = collect($this->spans())->first(
            fn ($s) => str_starts_with($s->getName(), 'SELECT')
        );

        $text = $span->getAttributes()->toArray()['db.query.text'];

        $this->assertStringNotContainsString('Jane Patient', $text);
        $this->assertStringNotContainsString('4242', $text);
        $this->assertStringContainsString('?', $text);
    }

    public function test_bindings_are_never_recorded(): void
    {
        $this->enableTracing(['observability.tracing.instrumentation.db.enabled' => true]);

        DB::select('select * from users where email = ?', ['patient@hospital.example']);

        foreach ($this->spans() as $span) {
            $this->assertStringNotContainsString(
                'patient@hospital.example',
                json_encode($span->getAttributes()->toArray())
            );
        }
    }

    public function test_the_slow_query_threshold_filters_fast_queries(): void
    {
        $this->enableTracing([
            'observability.tracing.instrumentation.db.enabled' => true,
            'observability.tracing.instrumentation.db.slow_query_ms' => 10000,
        ]);

        DB::table('users')->count();

        $dbSpans = collect($this->spans())->filter(
            fn ($s) => str_starts_with($s->getName(), 'SELECT')
        );

        $this->assertCount(0, $dbSpans);
    }
}
