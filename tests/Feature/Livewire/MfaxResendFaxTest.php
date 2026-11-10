<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Utilities\CloudFaxing;
use App\Models\DataSource;
use App\Models\User;
use App\Services\Faxing\FaxDashboardSnapshot;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

/**
 * Resend on the mFax status page: prefilled from the row, so it opens without waiting on
 * mFax, and it reports what happened, like the RingCentral page.
 */
class MfaxResendFaxTest extends TestCase
{
    use RefreshDatabase;

    private MockHandler $mfax;

    /** @var array<int, array{request: RequestInterface}> */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);

        DataSource::create(['mfax_api_key' => 'test-key']);

        Cache::put(FaxDashboardSnapshot::key('mfax'), [
            'files_to_send' => [], 'files_in_sent' => [], 'files_in_fail' => [], 'files_in_pre' => [],
            'files_to_send_count' => 0, 'files_in_sent_count' => 0, 'files_in_fail_count' => 0, 'files_in_pre_count' => 0,
            'generated_at' => now()->toIso8601String(),
        ], 180);

        $this->mfax = new MockHandler;
        $stack = HandlerStack::create($this->mfax);
        $stack->push(Middleware::history($this->requests));
        $this->app->instance(CloudFaxing::GUZZLE_BINDING, new Guzzle(['handler' => $stack, 'base_uri' => 'https://api.documo.com/']));

        // The page's cached fax history, which already carries each fax's recipient.
        Redis::shouldReceive('get')->andReturnUsing(fn (string $key) => match ($key) {
            'cloud-faxing:failed-faxes' => json_encode([[
                'messageId' => 'mfax-msg-1',
                'faxNumber' => '13042639659',
                'createdAt' => now()->toIso8601String(),
                'pagesComplete' => 0,
                'pagesCount' => 1,
                'faxAttempt' => 3,
                'status' => 'failed',
                'resultInfo' => 'Busy',
                'tags' => [],
            ]]),
            default => null,
        });
        Redis::shouldReceive('setEx')->andReturn(true);

        $this->actingAs(User::factory()->create());
    }

    public function test_the_dialog_is_prefilled_from_the_row_without_asking_mfax(): void
    {
        Livewire::test(CloudFaxing::class)
            ->mountAction('resendFax', ['messageId' => 'mfax-msg-1'])
            ->assertActionDataSet(['faxNumber' => '13042639659']);

        $this->assertCount(0, $this->requests);
    }

    public function test_a_fax_not_on_the_page_is_looked_up(): void
    {
        $this->mfax->append(new Response(200, [], json_encode(['messageId' => 'other', 'faxNumber' => '15551230000'])));

        Livewire::test(CloudFaxing::class)
            ->mountAction('resendFax', ['messageId' => 'other'])
            ->assertActionDataSet(['faxNumber' => '15551230000']);

        $this->assertSame('/v1/fax/other/info', $this->requests[0]['request']->getUri()->getPath());
    }

    public function test_a_resend_goes_to_the_number_in_the_dialog_and_says_so(): void
    {
        $this->mfax->append(
            new Response(200, [], '{}'),                       // resend
            new Response(200, [], json_encode(['rows' => []])), // tags
        );

        Livewire::test(CloudFaxing::class)
            ->mountAction('resendFax', ['messageId' => 'mfax-msg-1'])
            ->setActionData(['faxNumber' => '13045550100'])
            ->callMountedAction()
            ->assertNotified('Fax resent to 13045550100');

        parse_str((string) $this->requests[0]['request']->getBody(), $form);
        $this->assertSame(['messageId' => 'mfax-msg-1', 'recipientFax' => '13045550100'], $form);
    }

    public function test_a_rejected_resend_says_so(): void
    {
        $this->mfax->append(new Response(422, [], json_encode(['error' => 'bad number'])));

        Livewire::test(CloudFaxing::class)
            ->mountAction('resendFax', ['messageId' => 'mfax-msg-1'])
            ->callMountedAction()
            ->assertNotified('mFax did not resend the fax')
            ->assertNoRedirect();
    }

    public function test_the_resend_link_shows_it_is_working(): void
    {
        Livewire::test(CloudFaxing::class)
            ->assertSeeHtml('$wire.mountAction(')
            ->assertSeeHtml('x-bind:disabled="loading"');
    }
}
