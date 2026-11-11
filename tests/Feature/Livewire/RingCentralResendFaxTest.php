<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Console\Commands\ISFaxing\BuildRingCentralFaxDashboard;
use App\Livewire\Utilities\CloudFaxingRingCentral;
use App\Models\DataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\FakesRingCentral;

/**
 * Resend on the RingCentral status page.
 */
class RingCentralResendFaxTest extends TestCase
{
    use FakesRingCentral;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        DataSource::create([
            'ringcentral_client_id' => 'client-id',
            'ringcentral_client_secret' => 'client-secret',
            'ringcentral_api_endpoint' => 'https://platform.devtest.ringcentral.com',
            'ringcentral_jwt_token' => 'primary-jwt',
        ]);

        $this->fakeRingCentral(['primary-jwt' => '101']);

        // The page's dashboard snapshot, which already carries each fax's recipient.
        $this->redisWrites[BuildRingCentralFaxDashboard::DASHBOARD_CACHE_KEY] = json_encode([
            'failed_faxes' => [
                ['id' => '3280458140006', 'messageStatus' => 'SendingFailed', 'faxPageCount' => 1, 'to' => [['phoneNumber' => '+13042639659']]],
            ],
            'generated_at' => now()->toIso8601String(),
        ]);

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<string, mixed>
     */
    private function resendBody(): array
    {
        $request = end($this->ringCentralRequests)['request'];
        $contents = (string) $request->getBody();

        // The multipart body's JSON part carries the request fields.
        preg_match('/\{.*\}/s', $contents, $json);

        return json_decode($json[0] ?? '{}', true) ?? [];
    }

    public function test_the_dialog_is_prefilled_with_the_number_the_fax_went_to_without_asking_ringcentral(): void
    {
        Livewire::test(CloudFaxingRingCentral::class)
            ->mountAction('resendFax', ['messageId' => '3280458140006'])
            ->assertActionDataSet(['faxNumber' => '+13042639659']);

        $this->assertCount(0, $this->ringCentralRequests);
    }

    public function test_a_fax_missing_from_the_snapshot_is_looked_up(): void
    {
        $this->queueRingCentralResponse(200, [], ['id' => '999', 'to' => [['phoneNumber' => '+15551230000']]]);

        Livewire::test(CloudFaxingRingCentral::class)
            ->mountAction('resendFax', ['messageId' => '999'])
            ->assertActionDataSet(['faxNumber' => '+15551230000']);

        $this->assertStringEndsWith('/message-store/999', $this->ringCentralRequests[0]['request']->getUri()->getPath());
    }

    public function test_resending_unchanged_goes_to_the_original_recipient(): void
    {
        $this->queueRingCentralResponse(200);

        Livewire::test(CloudFaxingRingCentral::class)
            ->mountAction('resendFax', ['messageId' => '3280458140006'])
            ->callMountedAction()
            ->assertNotified('Fax resent to +13042639659');

        $this->assertSame(['originalMessageId' => '3280458140006'], $this->resendBody());
    }

    public function test_a_changed_number_is_sent_as_the_recipient(): void
    {
        $this->queueRingCentralResponse(200);

        Livewire::test(CloudFaxingRingCentral::class)
            ->mountAction('resendFax', ['messageId' => '3280458140006'])
            ->setActionData(['faxNumber' => '(304) 555-0100'])
            ->callMountedAction()
            ->assertNotified('Fax resent to +13045550100');

        $this->assertSame([
            'originalMessageId' => '3280458140006',
            'to' => [['phoneNumber' => '+13045550100']],
        ], $this->resendBody());
    }

    public function test_the_same_number_typed_differently_is_not_a_change(): void
    {
        $this->queueRingCentralResponse(200);

        Livewire::test(CloudFaxingRingCentral::class)
            ->mountAction('resendFax', ['messageId' => '3280458140006'])
            ->setActionData(['faxNumber' => '304-263-9659'])
            ->callMountedAction();

        $this->assertArrayNotHasKey('to', $this->resendBody());
    }

    public function test_a_rejected_resend_says_so(): void
    {
        $this->queueRingCentralResponse(400, [], ['errorCode' => 'FAX-101', 'message' => 'Original message not found']);

        Livewire::test(CloudFaxingRingCentral::class)
            ->mountAction('resendFax', ['messageId' => '3280458140006'])
            ->callMountedAction()
            ->assertNotified('RingCentral did not resend the fax')
            ->assertNoRedirect();
    }
}
