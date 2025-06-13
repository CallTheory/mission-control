<?php

namespace Tests\Feature\Console\Commands;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessingCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    /** @test */
    public function it_removes_old_wav_files()
    {
        // Create old and new wav files
        $oldFile = 'recordings/old.wav';
        $newFile = 'recordings/new.wav';

        Storage::put($oldFile, 'old-content');
        Storage::put($newFile, 'new-content');

        // Set the old file's last modified time to more than an hour ago
        Storage::setVisibility($oldFile, 'public');
        touch(Storage::path($oldFile), Carbon::now()->subHours(2)->timestamp);

        $this->artisan('app:processing-cleanup')
            ->expectsOutput('Checking recording wav files...')
            ->expectsOutput('Removing recordings/old.wav')
            ->expectsOutput('Checking transcription json files...')
            ->expectsOutput('Checking screen capture mp4 files...')
            ->expectsOutput('Complete!')
            ->assertExitCode(0);

        // Assert old file was removed
        $this->assertFalse(Storage::exists($oldFile));
        // Assert new file was preserved
        $this->assertTrue(Storage::exists($newFile));
    }

    /** @test */
    public function it_removes_old_json_files()
    {
        // Create old and new json files
        $oldFile = 'transcriptions/old.json';
        $newFile = 'transcriptions/new.json';

        Storage::put($oldFile, 'old-content');
        Storage::put($newFile, 'new-content');

        // Set the old file's last modified time to more than an hour ago
        Storage::setVisibility($oldFile, 'public');
        touch(Storage::path($oldFile), Carbon::now()->subHours(2)->timestamp);

        $this->artisan('app:processing-cleanup')
            ->expectsOutput('Checking recording wav files...')
            ->expectsOutput('Checking transcription json files...')
            ->expectsOutput('Removing transcriptions/old.json')
            ->expectsOutput('Checking screen capture mp4 files...')
            ->expectsOutput('Complete!')
            ->assertExitCode(0);

        // Assert old file was removed
        $this->assertFalse(Storage::exists($oldFile));
        // Assert new file was preserved
        $this->assertTrue(Storage::exists($newFile));
    }

    /** @test */
    public function it_removes_old_mp4_files()
    {
        // Create old and new mp4 files
        $oldFile = 'screencapture/old.mp4';
        $newFile = 'screencapture/new.mp4';

        Storage::put($oldFile, 'old-content');
        Storage::put($newFile, 'new-content');

        // Set the old file's last modified time to more than an hour ago
        Storage::setVisibility($oldFile, 'public');
        touch(Storage::path($oldFile), Carbon::now()->subHours(2)->timestamp);

        $this->artisan('app:processing-cleanup')
            ->expectsOutput('Checking recording wav files...')
            ->expectsOutput('Checking transcription json files...')
            ->expectsOutput('Checking screen capture mp4 files...')
            ->expectsOutput('Removing screencapture/old.mp4')
            ->expectsOutput('Complete!')
            ->assertExitCode(0);

        // Assert old file was removed
        $this->assertFalse(Storage::exists($oldFile));
        // Assert new file was preserved
        $this->assertTrue(Storage::exists($newFile));
    }

    /** @test */
    public function it_skips_files_with_incorrect_extensions()
    {
        // Create files with incorrect extensions
        $wavFile = 'recordings/test.txt';
        $jsonFile = 'transcriptions/test.txt';
        $mp4File = 'screencapture/test.txt';

        Storage::put($wavFile, 'content');
        Storage::put($jsonFile, 'content');
        Storage::put($mp4File, 'content');

        $this->artisan('app:processing-cleanup')
            ->expectsOutput('Checking recording wav files...')
            ->expectsOutput('Skipping recordings/test.txt')
            ->expectsOutput('Checking transcription json files...')
            ->expectsOutput('Skipping transcriptions/test.txt')
            ->expectsOutput('Checking screen capture mp4 files...')
            ->expectsOutput('Skipping screencapture/test.txt')
            ->expectsOutput('Complete!')
            ->assertExitCode(0);

        // Assert all files were preserved
        $this->assertTrue(Storage::exists($wavFile));
        $this->assertTrue(Storage::exists($jsonFile));
        $this->assertTrue(Storage::exists($mp4File));
    }
}
