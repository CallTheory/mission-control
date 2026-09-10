<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Utilities;

use App\Livewire\Utilities\ConfigEditor;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Amtelco configuration editor. The round trip is the whole point of the screen
 * and had no coverage: it decrypts a Triple DES blob, hands you the XML, and encrypts
 * whatever you hand back.
 */
class ConfigEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $team = Team::factory()->create(['personal_team' => false, 'utility_config_editor' => true]);
        $this->user->teams()->attach($team, ['role' => 'admin']);
        $this->user->switchTeam($team);
        $this->user = $this->user->fresh();
    }

    public function test_xml_survives_an_encrypt_decrypt_round_trip(): void
    {
        $xml = '<Config><Setting name="Timeout">30</Setting></Config>';

        $encrypted = Livewire::actingAs($this->user)
            ->test(ConfigEditor::class)
            ->set('xmlContent', $xml)
            ->callAction('encrypt')
            ->get('encryptedOutput');

        $this->assertNotEmpty($encrypted);
        $this->assertNotSame($xml, $encrypted);

        Livewire::actingAs($this->user)
            ->test(ConfigEditor::class)
            ->set('encryptedInput', $encrypted)
            ->callAction('decrypt')
            ->assertSet('xmlContent', $xml);
    }

    public function test_decrypting_nothing_reports_rather_than_throws(): void
    {
        Livewire::actingAs($this->user)
            ->test(ConfigEditor::class)
            ->set('encryptedInput', '')
            ->callAction('decrypt')
            ->assertOk()
            ->assertNotified();
    }

    public function test_decrypting_a_non_blob_reports_rather_than_throws(): void
    {
        Livewire::actingAs($this->user)
            ->test(ConfigEditor::class)
            ->set('encryptedInput', 'this is not base64 ciphertext')
            ->callAction('decrypt')
            ->assertOk()
            ->assertNotified();
    }

    public function test_encrypting_nothing_reports_rather_than_throws(): void
    {
        Livewire::actingAs($this->user)
            ->test(ConfigEditor::class)
            ->set('xmlContent', '')
            ->callAction('encrypt')
            ->assertOk()
            ->assertNotified();
    }

    public function test_the_write_back_actions_are_hidden_until_a_source_is_loaded(): void
    {
        $instance = Livewire::actingAs($this->user)->test(ConfigEditor::class)->instance();

        // Both overwrite Amtelco records, so neither is offered without a source.
        $this->assertFalse($instance->saveToScheduleAction()->isVisible());
        $this->assertFalse($instance->saveToEmailAccountAction()->isVisible());
    }

    public function test_the_write_back_actions_confirm_before_overwriting(): void
    {
        $instance = Livewire::actingAs($this->user)->test(ConfigEditor::class)->instance();

        foreach ([$instance->saveToScheduleAction(), $instance->saveToEmailAccountAction()] as $action) {
            $this->assertTrue($action->isConfirmationRequired());
            $this->assertSame('danger', $action->getColor());
        }
    }
}
