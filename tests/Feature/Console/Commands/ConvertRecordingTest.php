<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\WhisperCppTranscriptionJob;
use App\Models\Stats\Calls\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConvertRecordingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Redis::fake();
    }

    /** @test */
    public function it_converts_recording_and_dispatches_transcription_job()
    {
        $isCallId = 'test-call-123';
        
        // Mock the Call model and its recordingData method
        $call = $this->mock(Call::class);
        $call->shouldReceive('recordingData')
            ->once()
            ->andReturn(collect([
                (object) [
                    'fileID' => 'file1',
                    'Data' => 'mock-wav-data-1'
                ],
                (object) [
                    'fileID' => 'file2',
                    'Data' => 'mock-wav-data-2'
                ]
            ]));

        $this->artisan('recording:convert', ['isCallID' => $isCallId])
            ->expectsOutput('Found recordings/test-call-123/file1.wav')
            ->expectsOutput('Found recordings/test-call-123/file2.wav')
            ->assertExitCode(0);

        // Assert files were created
        $this->assertTrue(Storage::exists("recordings/{$isCallId}/file1.wav"));
        $this->assertTrue(Storage::exists("recordings/{$isCallId}/file2.wav"));

        // Assert Redis key was set
        Redis::assertExists("{$isCallId}.wav");

        // Assert transcription job was dispatched
        $this->assertDispatched(WhisperCppTranscriptionJob::class);
    }

    /** @test */
    public function it_handles_no_recordings()
    {
        $isCallId = 'test-call-123';
        
        // Mock the Call model to return empty collection
        $call = $this->mock(Call::class);
        $call->shouldReceive('recordingData')
            ->once()
            ->andReturn(collect([]));

        $this->artisan('recording:convert', ['isCallID' => $isCallId])
            ->assertExitCode(1);

        // Assert no files were created
        $this->assertFalse(Storage::exists("recordings/{$isCallId}"));
        $this->assertFalse(Storage::exists("recordings/{$isCallId}.wav"));

        // Assert no Redis key was set
        Redis::assertMissing("{$isCallId}.wav");

        // Assert no transcription job was dispatched
        $this->assertNotDispatched(WhisperCppTranscriptionJob::class);
    }

    /** @test */
    public function it_cleans_up_files_after_conversion()
    {
        $isCallId = 'test-call-123';
        
        // Mock the Call model
        $call = $this->mock(Call::class);
        $call->shouldReceive('recordingData')
            ->once()
            ->andReturn(collect([
                (object) [
                    'fileID' => 'file1',
                    'Data' => 'mock-wav-data-1'
                ]
            ]));

        $this->artisan('recording:convert', ['isCallID' => $isCallId])
            ->assertExitCode(0);

        // Assert temporary directory was cleaned up
        $this->assertFalse(Storage::exists("recordings/{$isCallId}"));

        // Assert final file exists
        $this->assertTrue(Storage::exists("recordings/{$isCallId}.wav"));
    }
} 