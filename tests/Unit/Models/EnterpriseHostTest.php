<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\EnterpriseHost;
use App\Models\Team;
use App\Models\WctpMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;

class EnterpriseHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_code_encryption(): void
    {
        $plainSecurityCode = 'secret123456';
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => $plainSecurityCode,
            'enabled' => true,
        ]);

        // Verify the security code is encrypted in the database
        $this->assertDatabaseHas('enterprise_hosts', [
            'id' => $host->id,
            'senderID' => 'test123',
        ]);

        // But the encrypted value should not match the plain text
        $rawData = \DB::table('enterprise_hosts')->where('id', $host->id)->first();
        $this->assertNotEquals($plainSecurityCode, $rawData->securityCode);

        // Verify we can decrypt it properly
        $decryptedCode = decrypt($rawData->securityCode);
        $this->assertEquals($plainSecurityCode, $decryptedCode);
    }

    public function test_security_code_decryption_via_attribute(): void
    {
        $plainSecurityCode = 'secret123456';
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => $plainSecurityCode,
            'enabled' => true,
        ]);

        // Verify the attribute accessor returns decrypted value
        $this->assertEquals($plainSecurityCode, $host->securityCode);
        
        // Refresh from database to ensure it's not just in memory
        $host->refresh();
        $this->assertEquals($plainSecurityCode, $host->securityCode);
    }

    public function test_null_security_code_handling(): void
    {
        // Since the database doesn't allow NULL, test the accessor behavior
        $host = EnterpriseHost::factory()->make(['securityCode' => null]);
        
        // The accessor should return null for null values
        $this->assertNull($host->securityCode);
    }

    public function test_empty_string_security_code_handling(): void
    {
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => 'validcode123', // Use a valid code first
            'enabled' => true,
        ]);

        // Test the mutator behavior with empty string
        $host->securityCode = '';
        
        // The mutator should convert empty string to null
        $this->assertNull($host->securityCode);
    }

    public function test_validate_security_code_success(): void
    {
        $plainSecurityCode = 'secret123456';
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => $plainSecurityCode,
            'enabled' => true,
        ]);

        $this->assertTrue($host->validateSecurityCode($plainSecurityCode));
    }

    public function test_validate_security_code_failure(): void
    {
        $plainSecurityCode = 'secret123456';
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => $plainSecurityCode,
            'enabled' => true,
        ]);

        $this->assertFalse($host->validateSecurityCode('wrongcode'));
        $this->assertFalse($host->validateSecurityCode(''));
        $this->assertFalse($host->validateSecurityCode('secret12345'));
    }

    public function test_validate_security_code_with_null_stored_code(): void
    {
        // Test with a host that has no security code set (via factory make)
        $host = EnterpriseHost::factory()->make(['securityCode' => null]);

        $this->assertFalse($host->validateSecurityCode('anycode'));
        $this->assertFalse($host->validateSecurityCode(''));
    }

    public function test_validate_security_code_handles_decryption_errors(): void
    {
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => 'valid_code',
            'enabled' => true,
        ]);

        // Manually corrupt the encrypted value in database
        \DB::table('enterprise_hosts')
            ->where('id', $host->id)
            ->update(['securityCode' => 'corrupted_encrypted_data']);

        // Clear any model cache
        $host = $host->fresh();

        // Should return false when decryption fails
        $this->assertFalse($host->validateSecurityCode('valid_code'));
    }

    public function test_team_relationship(): void
    {
        $team = Team::factory()->create(['name' => 'Test Team']);
        
        $host = EnterpriseHost::create([
            'name' => 'Test Host',
            'senderID' => 'test123',
            'securityCode' => 'secret123',
            'enabled' => true,
            'team_id' => $team->id,
        ]);

        $this->assertInstanceOf(Team::class, $host->team);
        $this->assertEquals('Test Team', $host->team->name);
    }

    public function test_messages_relationship(): void
    {
        $host = EnterpriseHost::factory()->create();
        
        WctpMessage::factory()->count(3)->create([
            'enterprise_host_id' => $host->id,
        ]);

        $this->assertCount(3, $host->messages);
        $this->assertInstanceOf(WctpMessage::class, $host->messages->first());
    }

    public function test_enabled_scope(): void
    {
        EnterpriseHost::factory()->create(['name' => 'Enabled Host', 'enabled' => true]);
        EnterpriseHost::factory()->create(['name' => 'Disabled Host', 'enabled' => false]);

        $enabledHosts = EnterpriseHost::enabled()->get();
        
        $this->assertCount(1, $enabledHosts);
        $this->assertEquals('Enabled Host', $enabledHosts->first()->name);
    }

    public function test_by_sender_id_scope(): void
    {
        EnterpriseHost::factory()->create(['senderID' => 'host1']);
        EnterpriseHost::factory()->create(['senderID' => 'host2']);

        $host = EnterpriseHost::bySenderID('host1')->first();
        
        $this->assertNotNull($host);
        $this->assertEquals('host1', $host->senderID);
    }

    public function test_record_message_increments_count_and_updates_timestamp(): void
    {
        $host = EnterpriseHost::factory()->create([
            'message_count' => 5,
            'last_message_at' => null,
        ]);

        $originalTime = now()->subHour();
        $this->travel($originalTime);

        $host->recordMessage();

        $host->refresh();
        $this->assertEquals(6, $host->message_count);
        $this->assertNotNull($host->last_message_at);
        $this->assertTrue($host->last_message_at->isAfter($originalTime));
    }

    public function test_record_message_multiple_calls(): void
    {
        $host = EnterpriseHost::factory()->create([
            'message_count' => 0,
        ]);

        $host->recordMessage();
        $host->recordMessage();
        $host->recordMessage();

        $host->refresh();
        $this->assertEquals(3, $host->message_count);
    }

    public function test_fillable_attributes(): void
    {
        $expectedFillable = [
            'name',
            'senderID',
            'securityCode',
            'enabled',
            'callback_url',
            'phone_numbers',
            'team_id',
            'message_count',
            'last_message_at',
        ];

        $host = new EnterpriseHost();
        $this->assertEquals($expectedFillable, $host->getFillable());
    }

    public function test_casts(): void
    {
        $host = new EnterpriseHost([
            'enabled' => '1',
            'message_count' => '42',
            'last_message_at' => '2023-01-01 12:00:00',
        ]);

        $this->assertIsBool($host->enabled);
        $this->assertTrue($host->enabled);
        $this->assertIsInt($host->message_count);
        $this->assertEquals(42, $host->message_count);
        $this->assertInstanceOf(\Carbon\Carbon::class, $host->last_message_at);
    }

    public function test_factory_creates_valid_host(): void
    {
        $host = EnterpriseHost::factory()->create();

        $this->assertNotNull($host->name);
        $this->assertNotNull($host->senderID);
        $this->assertNotNull($host->securityCode);
        $this->assertTrue($host->enabled);
        $this->assertEquals(0, $host->message_count);
        $this->assertNull($host->last_message_at);
    }

    public function test_factory_disabled_state(): void
    {
        $host = EnterpriseHost::factory()->disabled()->create();
        
        $this->assertFalse($host->enabled);
    }

    public function test_factory_with_team(): void
    {
        $host = EnterpriseHost::factory()->withTeam()->create();
        
        $this->assertNotNull($host->team_id);
        $this->assertInstanceOf(Team::class, $host->team);
    }

    public function test_factory_with_messages(): void
    {
        $host = EnterpriseHost::factory()->withMessages(5)->create();
        
        $this->assertEquals(5, $host->message_count);
        $this->assertNotNull($host->last_message_at);
    }

    public function test_security_code_update_preserves_encryption(): void
    {
        $host = EnterpriseHost::factory()->create([
            'securityCode' => 'original_code',
        ]);

        $originalEncrypted = \DB::table('enterprise_hosts')
            ->where('id', $host->id)
            ->value('securityCode');

        $host->update(['securityCode' => 'new_code']);

        $newEncrypted = \DB::table('enterprise_hosts')
            ->where('id', $host->id)
            ->value('securityCode');

        // Encrypted values should be different
        $this->assertNotEquals($originalEncrypted, $newEncrypted);
        
        // But the decrypted value should be correct
        $host->refresh();
        $this->assertEquals('new_code', $host->securityCode);
    }
}