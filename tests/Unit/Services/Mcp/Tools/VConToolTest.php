<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Mcp\Tools;

use App\Services\Mcp\Tools\VConTool;
use App\Services\VCon\VConGenerator;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;
use Mockery;

class VConToolTest extends TestCase
{
    private VConTool $tool;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new VConTool();
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    public function test_get_name_returns_correct_name(): void
    {
        $this->assertEquals('get_vcon_record', $this->tool->getName());
    }
    
    public function test_get_description_returns_description(): void
    {
        $description = $this->tool->getDescription();
        $this->assertStringContainsString('vCon', $description);
        $this->assertStringContainsString('call', $description);
    }
    
    public function test_get_input_schema_returns_valid_schema(): void
    {
        $schema = $this->tool->getInputSchema();
        
        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('callId', $schema['required']);
        
        // Check properties
        $properties = $schema['properties'];
        $this->assertArrayHasKey('callId', $properties);
        $this->assertArrayHasKey('includeRecording', $properties);
        $this->assertArrayHasKey('includeTranscription', $properties);
        
        // Check property types
        $this->assertEquals('string', $properties['callId']['type']);
        $this->assertEquals('boolean', $properties['includeRecording']['type']);
        $this->assertEquals('boolean', $properties['includeTranscription']['type']);
    }
    
    public function test_execute_requires_call_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('callId is required');
        
        $this->tool->execute([]);
    }
    
    public function test_execute_with_valid_call_id(): void
    {
        // Create a mock user
        $user = Mockery::mock('App\Models\User');
        $user->shouldReceive('getAttribute')->with('current_team_id')->andReturn(1);
        Auth::shouldReceive('user')->andReturn($user);
        
        // Mock the VConGenerator
        $mockGenerator = Mockery::mock('overload:' . VConGenerator::class);
        $mockGenerator->shouldReceive('generateFromCall')
            ->with('CALL-123', 1, true, true)
            ->once()
            ->andReturn([
                'vcon' => '0.0.1',
                'uuid' => 'test-uuid',
                'subject' => 'Call CALL-123',
                'parties' => [],
                'dialog' => []
            ]);
        
        $result = $this->tool->execute([
            'callId' => 'CALL-123',
            'includeRecording' => true,
            'includeTranscription' => true
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('0.0.1', $result['vcon']);
        $this->assertEquals('test-uuid', $result['uuid']);
        $this->assertEquals('Call CALL-123', $result['subject']);
    }
    
    public function test_execute_with_optional_parameters(): void
    {
        // Create a mock user
        $user = Mockery::mock('App\Models\User');
        $user->shouldReceive('getAttribute')->with('current_team_id')->andReturn(1);
        Auth::shouldReceive('user')->andReturn($user);
        
        // Mock the VConGenerator
        $mockGenerator = Mockery::mock('overload:' . VConGenerator::class);
        $mockGenerator->shouldReceive('generateFromCall')
            ->with('CALL-456', 1, false, false)
            ->once()
            ->andReturn([
                'vcon' => '0.0.1',
                'uuid' => 'test-uuid-2',
                'subject' => 'Call CALL-456'
            ]);
        
        $result = $this->tool->execute([
            'callId' => 'CALL-456',
            'includeRecording' => false,
            'includeTranscription' => false
        ]);
        
        $this->assertIsArray($result);
        $this->assertEquals('Call CALL-456', $result['subject']);
    }
    
    public function test_execute_with_default_optional_parameters(): void
    {
        // Create a mock user
        $user = Mockery::mock('App\Models\User');
        $user->shouldReceive('getAttribute')->with('current_team_id')->andReturn(1);
        Auth::shouldReceive('user')->andReturn($user);
        
        // Mock the VConGenerator - should use defaults (true, true)
        $mockGenerator = Mockery::mock('overload:' . VConGenerator::class);
        $mockGenerator->shouldReceive('generateFromCall')
            ->with('CALL-789', 1, true, true)
            ->once()
            ->andReturn([
                'vcon' => '0.0.1',
                'uuid' => 'test-uuid-3'
            ]);
        
        $result = $this->tool->execute([
            'callId' => 'CALL-789'
            // No includeRecording or includeTranscription provided
        ]);
        
        $this->assertIsArray($result);
    }
    
    public function test_execute_handles_generator_exception(): void
    {
        // Create a mock user
        $user = Mockery::mock('App\Models\User');
        $user->shouldReceive('getAttribute')->with('current_team_id')->andReturn(1);
        Auth::shouldReceive('user')->andReturn($user);
        
        // Mock the VConGenerator to throw an exception
        $mockGenerator = Mockery::mock('overload:' . VConGenerator::class);
        $mockGenerator->shouldReceive('generateFromCall')
            ->andThrow(new \Exception('Database error'));
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate vCon: Database error');
        
        $this->tool->execute(['callId' => 'CALL-ERROR']);
    }
    
    public function test_execute_without_authenticated_user(): void
    {
        Auth::shouldReceive('user')->andReturn(null);
        
        // Mock the VConGenerator
        $mockGenerator = Mockery::mock('overload:' . VConGenerator::class);
        $mockGenerator->shouldReceive('generateFromCall')
            ->with('CALL-NO-USER', null, true, true)
            ->once()
            ->andReturn([
                'vcon' => '0.0.1',
                'uuid' => 'test-uuid-no-user'
            ]);
        
        $result = $this->tool->execute(['callId' => 'CALL-NO-USER']);
        
        $this->assertIsArray($result);
    }
}