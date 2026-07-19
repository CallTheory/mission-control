<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use PHPUnit\Framework\TestCase;

class FaxFilePathTraversalTest extends TestCase
{
    private function traversalDetails(): array
    {
        return [
            'jobID' => '1',
            'capfile' => '../../../../etc/passwd.cap',
            'filename' => '../../secret.txt',
            'phone' => '+15551234567',
            'status' => '1',
            'fsFileName' => '../../evil.fs',
        ];
    }

    public function test_move_failed_strips_path_traversal_from_filenames(): void
    {
        $job = new MoveFailedFaxFiles($this->traversalDetails(), 'mfax');

        $this->assertSame('passwd.cap', $job->capfile);
        $this->assertSame('secret.txt', $job->filename);
        $this->assertSame('evil.fs', $job->fsFileName);
    }

    public function test_move_successful_strips_path_traversal_from_filenames(): void
    {
        $job = new MoveSuccessfulFaxFiles($this->traversalDetails(), 'mfax');

        $this->assertSame('passwd.cap', $job->capfile);
        $this->assertSame('secret.txt', $job->filename);
        $this->assertSame('evil.fs', $job->fsFileName);
    }
}
