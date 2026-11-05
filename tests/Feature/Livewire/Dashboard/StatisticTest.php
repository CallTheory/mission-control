<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Dashboard;

use App\Enums\DashboardTimeframe;
use App\Livewire\Dashboard\Statistic;
use App\Models\System\Settings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * No test environment has an Intelligent Series database, and no DataSource is created
 * here, so any tile that actually queries fails and reads 0. A tile reading anything
 * else therefore got its number from the cache.
 */
final class StatisticTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'dashboard_timeframe' => DashboardTimeframe::Last15Minutes->value,
        ]);

        $team = Team::forceCreate([
            'user_id' => $this->user->id,
            'name' => explode(' ', $this->user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]);

        $this->user->ownedTeams()->save($team);
        $this->user->switchTeam($team);

        Settings::factory()->create(['switch_data_timezone' => 'UTC']);

        $this->actingAs($this->user);
    }

    private function key(string $type = 'average_abandon_rate'): string
    {
        return Statistic::cacheKey($type, DashboardTimeframe::Last15Minutes, '', '');
    }

    public function test_a_cached_result_is_served_without_querying(): void
    {
        Cache::put($this->key(), 12.5, 60);

        Livewire::test(Statistic::class, ['type' => 'average_abandon_rate'])
            ->call('update')
            ->assertSet('metric', 12.5);
    }

    public function test_a_failed_query_is_not_cached(): void
    {
        Livewire::test(Statistic::class, ['type' => 'average_abandon_rate'])
            ->call('update')
            ->assertSet('metric', 0.0);

        $this->assertFalse(Cache::has($this->key()));
    }

    public function test_an_unavailable_cache_does_not_stop_the_tile(): void
    {
        Cache::shouldReceive('get')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));
        Cache::shouldReceive('put')->never();

        Livewire::test(Statistic::class, ['type' => 'average_abandon_rate'])
            ->call('update')
            ->assertSet('metric', 0.0);
    }

    public function test_a_zero_ttl_turns_the_cache_off(): void
    {
        config(['dashboard.cache_seconds' => 0]);
        Cache::put($this->key(), 12.5, 60);

        Livewire::test(Statistic::class, ['type' => 'average_abandon_rate'])
            ->call('update')
            ->assertSet('metric', 0.0);
    }

    public function test_viewers_asking_the_same_question_share_a_result(): void
    {
        $this->assertSame(
            Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, "100-199\n250", '7'),
            Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, "100-199\n250", '7'),
        );
    }

    public function test_a_different_question_gets_its_own_result(): void
    {
        $base = Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, '100', '7');

        $this->assertNotSame($base, Statistic::cacheKey('agent_abandons', DashboardTimeframe::LastHour, '100', '7'));
        $this->assertNotSame($base, Statistic::cacheKey('total_abandons', DashboardTimeframe::Last4Hours, '100', '7'));
        $this->assertNotSame($base, Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, '101', '7'));
        $this->assertNotSame($base, Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, '100', '8'));
        // The separator keeps a boundary shift between the two filters from colliding.
        $this->assertNotSame(
            Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, '1', '00'),
            Statistic::cacheKey('total_abandons', DashboardTimeframe::LastHour, '10', '0'),
        );
    }

    public function test_the_poll_interval_comes_from_config(): void
    {
        Livewire::test(Statistic::class, ['type' => 'average_abandon_rate'])
            ->assertSeeHtml('wire:poll.30000ms.visible="update"');

        config(['dashboard.poll_seconds' => 120]);

        Livewire::test(Statistic::class, ['type' => 'average_abandon_rate'])
            ->assertSeeHtml('wire:poll.120000ms.visible="update"');
    }
}
