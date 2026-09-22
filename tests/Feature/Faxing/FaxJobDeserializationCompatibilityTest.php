<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\MoveFailedFaxFiles;
use App\Jobs\MoveSuccessfulFaxFiles;
use App\Jobs\SendFaxJob;
use App\Jobs\SendFaxRingCentral;
use App\Models\DataSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Queued fax jobs outlive a deploy. SendFaxRingCentral releases itself back onto the
 * queue while throttled and bounds itself with retryUntil, so a payload serialized before
 * this change can be unserialized up to retry_window — two hours by default — after it.
 *
 * PHP's unserialize() applies declared property defaults and then overwrites from the
 * payload, so a property absent from an old payload keeps its default. A typed property
 * with NO default would instead be left uninitialized and fatal on first access — in
 * handle() and again in failed(), meaning the fax is neither sent nor reported and its
 * .fs sits in tosend until the buildup alert fires.
 *
 * Round-tripping the current class would prove none of this, so these tests work from
 * payloads that genuinely lack the property.
 */
class FaxJobDeserializationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A payload written by the build before spool sources existed: same class, same
     * property values, no spoolSourceKey entry at all.
     */
    private static function legacyPayload(string $class, string $provider): string
    {
        return sprintf(
            'O:%d:"%s":7:{s:5:"jobID";i:1;s:7:"capfile";s:8:"IS20.cap";s:8:"filename";s:8:"IS20.cap";'
            .'s:5:"phone";s:11:"19139069098";s:6:"status";s:1:"2";s:10:"fsFileName";s:7:"IS20.fs";'
            .'s:12:"fax_provider";s:%d:"%s";}',
            strlen($class),
            $class,
            strlen($provider),
            $provider,
        );
    }

    public function test_a_legacy_move_payload_unserializes_without_a_source(): void
    {
        $job = unserialize(self::legacyPayload(MoveSuccessfulFaxFiles::class, 'mfax'));

        $this->assertInstanceOf(MoveSuccessfulFaxFiles::class, $job);
        $this->assertNull($job->spoolSourceKey);

        // Resolves to the legacy provider-named source, which is where it came from.
        $this->assertSame('mfax', $job->sourceKey());
    }

    public function test_a_legacy_move_payload_releases_the_lock_it_originally_took(): void
    {
        // The expensive failure: if uniqueId() changed shape, the post-deploy release
        // would target a key nothing holds, and the original lock would stand for the
        // full uniqueFor window with that .fs undispatchable and nothing logged.
        foreach ([MoveSuccessfulFaxFiles::class, MoveFailedFaxFiles::class] as $class) {
            foreach (['mfax', 'ringcentral'] as $provider) {
                $job = unserialize(self::legacyPayload($class, $provider));

                $this->assertSame('IS20.fs', $job->uniqueId(), "{$class} / {$provider}");
            }
        }
    }

    public function test_a_legacy_move_payload_still_resolves_its_spool_paths(): void
    {
        $job = unserialize(self::legacyPayload(MoveFailedFaxFiles::class, 'ringcentral'));

        // Path building is the first thing handle() does; an uninitialized property would
        // fatal here rather than anywhere diagnosable.
        $job->handle();

        $this->assertTrue(true, 'handle() completed without touching an uninitialized property');
    }

    public function test_a_legacy_source_job_serializes_exactly_as_it_did_before(): void
    {
        // SerializesModels omits any property whose value equals its declared default, so
        // a job on a legacy source produces a payload with no spoolSourceKey entry —
        // byte-identical to what the previous build wrote. This is what makes the upgrade
        // a non-event in both directions.
        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'mfax_cover_page_id' => 'test-cover-page',
            'mfax_subject' => 'Test Subject',
            'mfax_sender_name' => 'Test Sender',
            'mfax_notes' => 'Test notes',
        ]);

        $details = [
            'jobID' => 1,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
        ];

        foreach ([new SendFaxJob($details), new SendFaxRingCentral($details), new MoveSuccessfulFaxFiles($details, 'mfax')] as $job) {
            $this->assertStringNotContainsString('spoolSourceKey', serialize($job), $job::class);
        }
    }

    public function test_a_non_legacy_source_is_carried_in_the_payload(): void
    {
        $job = new MoveSuccessfulFaxFiles([
            'jobID' => 1,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
        ], 'mfax', 'is2');

        $restored = unserialize(serialize($job));

        $this->assertSame('is2', $restored->sourceKey());
        $this->assertSame('is2:IS20.fs', $restored->uniqueId());
    }
}
