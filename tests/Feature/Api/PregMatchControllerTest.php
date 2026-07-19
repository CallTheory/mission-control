<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\API\Utilities\PregMatchController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PregMatchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        $this->app->forgetInstance('cache');
        $this->app->forgetInstance('cache.store');
        $this->withoutMiddleware(ThrottleRequests::class);

        // Route is registered only when the api-gateway feature is enabled at boot.
        Route::middleware('api')->prefix('api')->group(function () {
            Route::match(['get', 'post'], '/utilities/preg-match', PregMatchController::class)
                ->name('api.utilities.preg-match');
        });
    }

    public function test_normal_pattern_matches(): void
    {
        $this->postJson('/api/utilities/preg-match', [
            'string' => 'abc123def456',
            'pattern' => '/\d+/',
        ])->assertOk()->assertExactJson(['123', '456']);
    }

    public function test_oversized_string_is_rejected(): void
    {
        $this->postJson('/api/utilities/preg-match', [
            'string' => str_repeat('a', 5000),
            'pattern' => '/a/',
        ])->assertStatus(400);
    }

    public function test_oversized_pattern_is_rejected(): void
    {
        $this->postJson('/api/utilities/preg-match', [
            'string' => 'abc',
            'pattern' => '/'.str_repeat('a', 600).'/',
        ])->assertStatus(400);
    }

    public function test_catastrophic_pattern_bails_out_instead_of_hanging(): void
    {
        // Classic ReDoS pattern against non-matching input. The lowered backtrack
        // limit makes preg_match_all return false (→ empty result) rather than
        // pinning the worker. The test completing promptly IS the assertion.
        $start = microtime(true);

        $response = $this->postJson('/api/utilities/preg-match', [
            'string' => str_repeat('a', 40).'X',
            'pattern' => '/^(a+)+$/',
        ]);

        $elapsed = microtime(true) - $start;

        $response->assertOk()->assertExactJson([]);
        $this->assertLessThan(5, $elapsed, 'preg matching should be bounded, not catastrophic');
    }
}
