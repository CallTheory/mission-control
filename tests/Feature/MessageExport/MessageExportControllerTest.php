<?php

declare(strict_types=1);

namespace Tests\Feature\MessageExport;

use App\Models\MessageExportLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MessageExportControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->team = Team::factory()->create([
            'personal_team' => false,
            'utility_message_export' => true,
        ]);

        $this->user->teams()->attach($this->team, ['role' => 'admin']);
        $this->user->switchTeam($this->team);
        $this->user = $this->user->fresh();
    }

    protected function tearDown(): void
    {
        Storage::deleteDirectory('feature-flags');
        Storage::deleteDirectory('message-exports');
        parent::tearDown();
    }

    public function test_it_returns_404_when_system_feature_flag_is_disabled(): void
    {
        // Ensure no feature flag exists
        Storage::deleteDirectory('feature-flags');

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export'));

        $response->assertStatus(404);
    }

    public function test_it_returns_404_when_team_utility_is_disabled(): void
    {
        $this->enableSystemFeatureFlag();

        $this->team->utility_message_export = false;
        $this->team->save();

        $this->user->unsetRelation('currentTeam');
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export'));

        $response->assertStatus(404);
    }

    public function test_it_returns_403_for_personal_team(): void
    {
        $this->enableSystemFeatureFlag();

        $personalTeam = Team::factory()->create([
            'personal_team' => true,
            'utility_message_export' => true,
        ]);

        $this->user->teams()->attach($personalTeam, ['role' => 'admin']);
        $this->user->switchTeam($personalTeam);
        $this->user = $this->user->fresh();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export'));

        $response->assertStatus(403);
    }

    public function test_it_allows_access_when_feature_and_utility_enabled(): void
    {
        $this->enableSystemFeatureFlag();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export'));

        $response->assertStatus(200)
            ->assertViewIs('utilities.message-export');
    }

    public function test_it_requires_authentication(): void
    {
        $this->enableSystemFeatureFlag();

        $response = $this->get(route('utilities.message-export'));

        $response->assertRedirect(route('login'));
    }

    public function test_history_page_loads_successfully(): void
    {
        $this->enableSystemFeatureFlag();

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export.history'));

        $response->assertStatus(200)
            ->assertViewIs('utilities.message-export-history');
    }

    public function test_history_page_returns_404_when_feature_disabled(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export.history'));

        $response->assertStatus(404);
    }

    public function test_download_serves_decrypted_csv(): void
    {
        $this->enableSystemFeatureFlag();

        $csvContent = "Message ID,Call ID\n123,456\n";
        $filePath = 'message-exports/test-download.csv';
        Storage::put($filePath, encrypt($csvContent));

        $log = MessageExportLog::factory()->completed()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'file_path' => $filePath,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export.download', $log));

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_download_returns_403_for_other_team_log(): void
    {
        $this->enableSystemFeatureFlag();

        $otherTeam = Team::factory()->create();
        $log = MessageExportLog::factory()->completed()->create([
            'team_id' => $otherTeam->id,
            'file_path' => 'message-exports/other.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export.download', $log));

        $response->assertStatus(403);
    }

    public function test_download_returns_404_when_file_missing(): void
    {
        $this->enableSystemFeatureFlag();

        $log = MessageExportLog::factory()->completed()->create([
            'team_id' => $this->team->id,
            'user_id' => $this->user->id,
            'file_path' => 'message-exports/nonexistent.csv',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('utilities.message-export.download', $log));

        $response->assertStatus(404);
    }

    private function enableSystemFeatureFlag(): void
    {
        Storage::makeDirectory('feature-flags');
        $encrypted = encrypt('message-export');
        Storage::put('feature-flags/message-export.flag', $encrypted);
    }
}
