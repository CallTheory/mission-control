<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp\Tools;

use App\Models\Stats\Calls\Call;
use App\Models\User;
use App\Services\Mcp\Tools\CallRecordingTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Testable subclass that allows overriding protected methods
 */
class TestableCallRecordingTool extends CallRecordingTool
{
    private ?Call $mockCall = null;

    private bool $skipAccessCheck = false;

    public function setMockCall(?Call $call): void
    {
        $this->mockCall = $call;
    }

    public function setSkipAccessCheck(bool $skip): void
    {
        $this->skipAccessCheck = $skip;
    }

    protected function getCall(string $isCallId): Call
    {
        if ($this->mockCall !== null) {
            return $this->mockCall;
        }

        return parent::getCall($isCallId);
    }

    protected function checkAccess(Call $call): void
    {
        if ($this->skipAccessCheck) {
            return;
        }

        parent::checkAccess($call);
    }
}

class CallRecordingToolTest extends TestCase
{
    use RefreshDatabase;

    private CallRecordingTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new CallRecordingTool;
    }

    public function test_tool_name_is_correct(): void
    {
        $this->assertEquals('get_call_recording', $this->tool->getName());
    }

    public function test_tool_description_is_set(): void
    {
        $description = $this->tool->getDescription();
        $this->assertNotEmpty($description);
        $this->assertStringContainsString('MP3', $description);
        $this->assertStringContainsString('IsCallId', $description);
    }

    public function test_input_schema_has_required_fields(): void
    {
        $schema = $this->tool->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('isCallId', $schema['properties']);
        $this->assertEquals('string', $schema['properties']['isCallId']['type']);
        $this->assertContains('isCallId', $schema['required']);
    }

    public function test_throws_exception_when_iscallid_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('isCallId is required');

        $this->tool->execute([]);
    }

    public function test_throws_exception_when_iscallid_is_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('isCallId is required');

        $this->tool->execute(['isCallId' => null]);
    }

    public function test_throws_exception_when_iscallid_is_not_numeric(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('isCallId must be a valid numeric identifier');

        $this->tool->execute(['isCallId' => 'abc-invalid']);
    }

    public function test_throws_exception_when_iscallid_contains_special_characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('isCallId must be a valid numeric identifier');

        $this->tool->execute(['isCallId' => '123-456']);
    }

    public function test_returns_cached_mp3_when_available(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '12345';
        $fakeMp3Data = 'fake-mp3-binary-data';

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $mockCall->shouldReceive('__get')->with('ClientNumber')->andReturn('');
        $mockCall->shouldReceive('__get')->with('BillingCode')->andReturn('');
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        // Mock Redis to return cached MP3
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn($fakeMp3Data);

        $result = $tool->execute(['isCallId' => $isCallId]);

        $this->assertIsArray($result);
        $this->assertEquals($isCallId, $result['isCallId']);
        $this->assertEquals('mp3', $result['format']);
        $this->assertEquals('base64', $result['encoding']);
        $this->assertEquals(base64_encode($fakeMp3Data), $result['data']);
        $this->assertEquals(strlen($fakeMp3Data), $result['sizeBytes']);
        $this->assertTrue($result['cached']);
    }

    public function test_triggers_conversion_when_mp3_not_cached(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '67890';
        $fakeMp3Data = 'converted-mp3-binary-data';

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        // First call returns null (not cached)
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn(null);

        // Mock the Artisan command for conversion
        Artisan::shouldReceive('call')
            ->once()
            ->with('recording:convert-mp3', ['isCallID' => $isCallId])
            ->andReturn(0);

        // Second call returns the converted MP3
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn($fakeMp3Data);

        $result = $tool->execute(['isCallId' => $isCallId]);

        $this->assertIsArray($result);
        $this->assertEquals($isCallId, $result['isCallId']);
        $this->assertEquals('mp3', $result['format']);
        $this->assertFalse($result['cached']);
    }

    public function test_throws_exception_when_conversion_fails(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '99999';

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        // Not cached
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn(null);

        // Conversion fails (non-zero exit code)
        Artisan::shouldReceive('call')
            ->once()
            ->with('recording:convert-mp3', ['isCallID' => $isCallId])
            ->andReturn(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to convert recording to MP3');

        $tool->execute(['isCallId' => $isCallId]);
    }

    public function test_throws_exception_when_mp3_not_found_after_conversion(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '11111';

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        // Not cached initially
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn(null);

        // Conversion succeeds
        Artisan::shouldReceive('call')
            ->once()
            ->with('recording:convert-mp3', ['isCallID' => $isCallId])
            ->andReturn(0);

        // Still not in cache after conversion (edge case)
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Recording conversion completed but MP3 data not found');

        $tool->execute(['isCallId' => $isCallId]);
    }

    public function test_validates_numeric_string_iscallid(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '12345';
        $fakeMp3Data = 'fake-mp3-data';

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        // Should accept numeric string and proceed
        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn($fakeMp3Data);

        $result = $tool->execute(['isCallId' => $isCallId]);

        $this->assertEquals($isCallId, $result['isCallId']);
    }

    public function test_response_format_matches_expected_schema(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '54321';
        $fakeMp3Data = 'test-mp3-content';

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn($fakeMp3Data);

        $result = $tool->execute(['isCallId' => $isCallId]);

        // Verify all expected fields are present
        $this->assertArrayHasKey('isCallId', $result);
        $this->assertArrayHasKey('format', $result);
        $this->assertArrayHasKey('encoding', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('sizeBytes', $result);
        $this->assertArrayHasKey('cached', $result);

        // Verify field types
        $this->assertIsString($result['isCallId']);
        $this->assertIsString($result['format']);
        $this->assertIsString($result['encoding']);
        $this->assertIsString($result['data']);
        $this->assertIsInt($result['sizeBytes']);
        $this->assertIsBool($result['cached']);
    }

    public function test_base64_encoding_is_valid(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        $isCallId = '77777';
        $fakeMp3Data = random_bytes(100); // Binary data

        // Create testable tool with mock call
        $tool = new TestableCallRecordingTool;
        $mockCall = Mockery::mock(Call::class);
        $tool->setMockCall($mockCall);
        $tool->setSkipAccessCheck(true);

        Redis::shouldReceive('get')
            ->once()
            ->with("{$isCallId}.mp3")
            ->andReturn($fakeMp3Data);

        $result = $tool->execute(['isCallId' => $isCallId]);

        // Verify the base64 can be decoded back to original
        $decoded = base64_decode($result['data'], true);
        $this->assertNotFalse($decoded);
        $this->assertEquals($fakeMp3Data, $decoded);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
