<?php

declare(strict_types=1);

namespace Tests\Feature\Faxing;

use App\Jobs\SendFaxJob;
use App\Models\DataSource;
use App\Services\Faxing\FaxLockKey;
use Illuminate\Bus\UniqueLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaxLockKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_legacy_source_keeps_the_original_bare_key(): void
    {
        // The upgrade-safety rule. A job serialized before sources existed releases its
        // lock against whatever uniqueId() returns after the deploy; if that changed
        // shape the release would miss and hold the lock for the whole uniqueFor window.
        $this->assertSame('IS20.fs', FaxLockKey::for('mfax', 'mfax', 'IS20.fs'));
        $this->assertSame('IS20.fs', FaxLockKey::for('ringcentral', 'ringcentral', 'IS20.fs'));
    }

    public function test_a_payload_with_no_source_falls_back_to_the_legacy_key(): void
    {
        // Exactly what an in-flight job unserialized after the deploy looks like.
        $this->assertSame('IS20.fs', FaxLockKey::for(null, 'mfax', 'IS20.fs'));
    }

    public function test_a_new_source_is_namespaced(): void
    {
        $this->assertSame('is2:IS20.fs', FaxLockKey::for('is2', 'mfax', 'IS20.fs'));
    }

    public function test_two_sources_never_share_a_key_for_the_same_fs_name(): void
    {
        // The collision that silently drops a fax: PendingDispatch::__destruct discards a
        // ShouldBeUnique job whose lock is already held, with nothing logged.
        $keys = [
            FaxLockKey::for('mfax', 'mfax', 'IS20.fs'),
            FaxLockKey::for('is2', 'mfax', 'IS20.fs'),
            FaxLockKey::for('is3', 'mfax', 'IS20.fs'),
        ];

        $this->assertCount(3, array_unique($keys));
    }

    public function test_it_matches_the_key_the_framework_actually_locks_on(): void
    {
        // FaxSpool::releaseUniqueLock() rebuilds this string by hand, so it has to track
        // Illuminate\Bus\UniqueLock::getKey() — which hashes displayName() for any job
        // that defines one. Neither send job does today; this fails loudly if that changes.
        DataSource::create([
            'mfax_api_key' => encrypt('test-api-key'),
            'mfax_cover_page_id' => 'test-cover-page',
            'mfax_subject' => 'Test Subject',
            'mfax_sender_name' => 'Test Sender',
            'mfax_notes' => 'Test notes',
        ]);

        $job = new SendFaxJob([
            'jobID' => 4242,
            'capfile' => 'IS20.cap',
            'filename' => 'IS20.cap',
            'phone' => '9139069098',
            'status' => '2',
            'fsFileName' => 'IS20.fs',
        ]);

        $this->assertSame(
            'laravel_unique_job:'.SendFaxJob::class.':'.FaxLockKey::for(null, 'mfax', 'IS20.fs'),
            UniqueLock::getKey($job)
        );
    }
}
