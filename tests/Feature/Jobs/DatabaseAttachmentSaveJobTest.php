<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\DatabaseAttachmentSaveJob;
use App\Models\DataSource;
use App\Models\InboundEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DatabaseAttachmentSaveJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DataSource::create([]); // firstOrFail() in the constructor needs a row.
    }

    private function runAction(string $action): InboundEmail
    {
        $email = InboundEmail::create(['to' => 'a@b.com', 'from' => 'c@d.com']);
        (new DatabaseAttachmentSaveJob($email, $action))->handle();

        return $email->refresh();
    }

    public function test_merge_action_is_now_accepted_and_reaches_the_merge_branch(): void
    {
        Log::spy();

        // client_db_* is unconfigured, so the merge branch runs but fails at
        // validateDatabaseConnection — the point is that merge is no longer rejected
        // by the action validator (which previously only allowed 'replace').
        $email = $this->runAction('database:merge:client_table:external_id');

        Log::shouldHaveReceived('error')->withArgs(
            fn ($message, $context = []) => str_contains((string) $message, 'Unable to merge database')
        );
        Log::shouldNotHaveReceived('error', ['Invalid action trying to parse database action']);

        $this->assertNotNull($email->processed_at);
    }

    public function test_replace_action_still_works(): void
    {
        Log::spy();

        $email = $this->runAction('database:replace:client_table');

        Log::shouldHaveReceived('error')->withArgs(
            fn ($message, $context = []) => str_contains((string) $message, 'Unable to replace database')
        );

        $this->assertNotNull($email->processed_at);
    }

    public function test_unknown_action_is_rejected_by_validation(): void
    {
        Log::spy();

        $email = $this->runAction('database:bogus:client_table');

        Log::shouldHaveReceived('error')->withArgs(
            fn ($message, $context = []) => str_contains((string) $message, 'Invalid action trying to parse database action')
        );

        $this->assertNotNull($email->processed_at);
    }

    public function test_constructor_parses_column_for_pk(): void
    {
        $email = InboundEmail::create(['to' => 'a@b.com', 'from' => 'c@d.com']);
        $job = new DatabaseAttachmentSaveJob($email, 'database:merge:client_table:external_id');

        $ref = new \ReflectionProperty($job, 'database_commands');
        $ref->setAccessible(true);
        $commands = $ref->getValue($job);

        $this->assertSame('merge', $commands['action']);
        $this->assertSame('client_table', $commands['table_name']);
        $this->assertSame('external_id', $commands['column_for_pk']);
    }
}
