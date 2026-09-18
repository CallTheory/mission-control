<?php

declare(strict_types=1);

namespace Tests\Feature\System\AzureTokens;

use App\Mail\AzureCredentialExpiryAlert;
use App\Models\AzureCredential;
use App\Models\System\Settings;
use App\Services\Azure\ExpiryAlerter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Threshold crossing and, above all, its idempotency: a daily sweep that emails
 * daily is an alert nobody reads by the time the secret actually expires.
 */
class ExpiryAlerterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        Settings::create([
            'azure_tokens_alert_enabled' => true,
            'azure_tokens_alert_recipients' => 'identity@example.com',
        ]);
    }

    private function alerter(): ExpiryAlerter
    {
        return new ExpiryAlerter;
    }

    public static function thresholdCases(): array
    {
        return [
            '29 days crosses 30' => [29, 30],
            '14 days crosses 14' => [14, 14],
            '4 days is still the 14 band' => [4, 14],
            '2 days crosses 3' => [2, 3],
            'expired crosses 0' => [-2, 0],
        ];
    }

    #[DataProvider('thresholdCases')]
    public function test_it_records_the_threshold_a_credential_crossed(int $days, int $expected): void
    {
        $credential = $days < 0
            ? AzureCredential::factory()->expired(abs($days))->create()
            : AzureCredential::factory()->expiringInDays($days)->create();

        $this->assertSame(1, $this->alerter()->run());
        $this->assertSame($expected, $credential->fresh()->alerted_threshold);

        Mail::assertQueued(AzureCredentialExpiryAlert::class);
    }

    public function test_a_credential_outside_every_threshold_is_not_alerted_on(): void
    {
        AzureCredential::factory()->expiringInDays(90)->create();

        $this->assertSame(0, $this->alerter()->run());

        Mail::assertNothingQueued();
    }

    public function test_the_same_threshold_is_not_alerted_on_twice(): void
    {
        AzureCredential::factory()->expiringInDays(20)->create();

        $this->assertSame(1, $this->alerter()->run());
        $this->assertSame(0, $this->alerter()->run());

        Mail::assertQueuedCount(1);
    }

    public function test_crossing_the_next_threshold_alerts_again(): void
    {
        $credential = AzureCredential::factory()->expiringInDays(20)->create();

        $this->alerter()->run();

        // The same credential, now inside the 14 day band.
        $credential->update(['end_utc' => now()->addDays(10)]);

        $this->assertSame(1, $this->alerter()->run());
        $this->assertSame(14, $credential->fresh()->alerted_threshold);
        Mail::assertQueuedCount(2);
    }

    public function test_acknowledged_credentials_are_skipped(): void
    {
        AzureCredential::factory()->acknowledged()->expiringInDays(2)->create();

        $this->assertSame(0, $this->alerter()->run());
        Mail::assertNothingQueued();
    }

    public function test_credentials_azure_no_longer_returns_are_skipped(): void
    {
        AzureCredential::factory()->removed()->expiringInDays(2)->create();

        $this->assertSame(0, $this->alerter()->run());
        Mail::assertNothingQueued();
    }

    /**
     * Turning alerting on later should report what is already overdue rather than
     * start from silence because the sweeps ran first.
     */
    public function test_with_alerting_off_nothing_is_sent_and_no_threshold_is_burned(): void
    {
        Settings::first()->update(['azure_tokens_alert_enabled' => false]);
        $credential = AzureCredential::factory()->expiringInDays(2)->create();

        $this->assertSame(0, $this->alerter()->run());
        $this->assertNull($credential->fresh()->alerted_threshold);

        Settings::first()->update(['azure_tokens_alert_enabled' => true]);

        $this->assertSame(1, $this->alerter()->run());
        $this->assertSame(3, $credential->fresh()->alerted_threshold);
    }

    public function test_with_no_recipients_nothing_is_sent_and_no_threshold_is_burned(): void
    {
        Settings::first()->update(['azure_tokens_alert_recipients' => null]);
        $credential = AzureCredential::factory()->expiringInDays(2)->create();

        $this->assertSame(0, $this->alerter()->run());
        $this->assertNull($credential->fresh()->alerted_threshold);
    }

    public function test_everything_crossing_at_once_is_one_digest(): void
    {
        AzureCredential::factory()->count(3)->expiringInDays(2)->create();
        AzureCredential::factory()->expired()->create();

        $this->assertSame(4, $this->alerter()->run());

        Mail::assertQueuedCount(1);
        Mail::assertQueued(AzureCredentialExpiryAlert::class, function (AzureCredentialExpiryAlert $mail): bool {
            return count($mail->credentials) === 4
                && $mail->recipients === ['identity@example.com']
                && str_contains($mail->subjectLine(), 'expired');
        });
    }

    public function test_recipients_are_split_on_commas_and_newlines(): void
    {
        $this->assertSame(
            ['one@example.com', 'two@example.com'],
            ExpiryAlerter::recipients("one@example.com,\n two@example.com\n"),
        );

        $this->assertSame([], ExpiryAlerter::recipients('  '));
        $this->assertSame([], ExpiryAlerter::recipients('not-an-address'));
    }
}
