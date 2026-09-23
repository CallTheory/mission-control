<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxRingCentral;
use App\Models\DataSource;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every ShouldBeUnique fax job must bound its lock.
 *
 * Laravel takes the lock with no expiry when a job omits uniqueFor (UniqueLock::acquire
 * falls back to `uniqueFor ?? 0`) and releases it only on completion, so a worker killed
 * mid-job strands it permanently. Intelligent Series reuses `.fs` filenames, which are
 * what these jobs key on, so one stranded lock silently suppressed that filename for
 * ever.
 *
 * For the move jobs that meant duplicate faxes reaching recipients: the .fs was never
 * moved out of tosend/, the submission dedupe only suppresses rows still `pending`, and
 * so the next scan sent the same fax again every few minutes.
 */
class FaxJobUniqueLockExpiryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        return [
            'jobID' => 4242,
            'capfile' => 'IS342.cap',
            'filename' => 'IS342.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS342.fs',
        ];
    }

    private function seedDataSource(): void
    {
        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'ringcentral_client_id' => 'id',
            'ringcentral_client_secret' => encrypt('secret'),
            'ringcentral_jwt_token' => encrypt('jwt'),
            'ringcentral_api_endpoint' => 'https://platform.ringcentral.com',
        ]);
    }

    public function test_every_unique_fax_job_expires_its_lock(): void
    {
        $this->seedDataSource();

        $jobs = [
            new MoveSuccessfulFaxFiles($this->details(), 'ringcentral'),
            new MoveFailedFaxFiles($this->details(), 'ringcentral'),
            new SendFaxJob($this->details()),
            new SendFaxRingCentral($this->details()),
        ];

        foreach ($jobs as $job) {
            $this->assertInstanceOf(ShouldBeUnique::class, $job);

            $this->assertTrue(
                method_exists($job, 'uniqueFor'),
                $job::class.' takes a unique lock with no expiry. A worker killed mid-job '
                .'strands it for ever, and .fs filenames are reused.'
            );

            $this->assertGreaterThan(
                0,
                $job->uniqueFor(),
                $job::class.'::uniqueFor() must be a positive number of seconds.'
            );
        }
    }

    public function test_the_move_lock_outlives_a_normal_move_but_not_the_day(): void
    {
        $this->seedDataSource();

        $window = (new MoveSuccessfulFaxFiles($this->details(), 'ringcentral'))->uniqueFor();

        // Long enough that two genuine moves of the same file cannot overlap...
        $this->assertGreaterThanOrEqual(600, $window);
        // ...short enough that a stranded lock self-heals rather than needing a human.
        $this->assertLessThanOrEqual(7200, $window);
    }
}
