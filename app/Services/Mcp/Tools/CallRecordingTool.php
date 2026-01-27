<?php

declare(strict_types=1);

namespace App\Services\Mcp\Tools;

use App\Models\Stats\Calls\Call;
use App\Models\Stats\Helpers;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redis;

class CallRecordingTool implements ToolInterface
{
    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return 'get_call_recording';
    }

    /**
     * Get the tool description
     */
    public function getDescription(): string
    {
        return 'Retrieve a call recording in MP3 format by its IsCallId. Returns base64-encoded audio data.';
    }

    /**
     * Get the input schema for the tool
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'isCallId' => [
                    'type' => 'string',
                    'description' => 'The IsCallId (Intelligent Switch Call ID) of the recording',
                ],
            ],
            'required' => ['isCallId'],
        ];
    }

    /**
     * Execute the tool with given arguments
     */
    public function execute(array $arguments): mixed
    {
        $isCallId = $arguments['isCallId'] ?? null;

        if (! $isCallId) {
            throw new \InvalidArgumentException('isCallId is required');
        }

        // Validate isCallId format (should be numeric)
        if (! ctype_digit((string) $isCallId)) {
            throw new \InvalidArgumentException('isCallId must be a valid numeric identifier');
        }

        // Get the call to verify it exists and check access
        $call = $this->getCall($isCallId);

        // Check user has access to this recording
        $this->checkAccess($call);

        // Get or convert the recording to MP3
        return $this->getOrConvertMp3($isCallId);
    }

    /**
     * Get the Call object for the given isCallId
     *
     * @throws \RuntimeException
     */
    protected function getCall(string $isCallId): Call
    {
        try {
            return new Call(['ISCallId' => $isCallId]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Call not found or unable to retrieve call data');
        }
    }

    /**
     * Check if the current user has access to the call recording
     *
     * @throws \RuntimeException
     */
    protected function checkAccess(Call $call): void
    {
        $user = Auth::user();
        if ($user) {
            $allowedAccounts = $user->currentTeam->allowed_accounts ?? '';
            $allowedBilling = $user->currentTeam->allowed_billing ?? '';

            if (! Helpers::allowedAccountAccess(
                $call->ClientNumber ?? '',
                $call->BillingCode ?? '',
                $allowedAccounts,
                $allowedBilling
            )) {
                throw new \RuntimeException('Access denied to this recording');
            }
        }
    }

    /**
     * Get the MP3 from cache or trigger conversion
     *
     * @throws \RuntimeException
     */
    protected function getOrConvertMp3(string $isCallId): array
    {
        $cacheKey = "{$isCallId}.mp3";
        $mp3Data = Redis::get($cacheKey);
        $cached = true;

        if (! $mp3Data) {
            $cached = false;

            // Trigger conversion synchronously
            $exitCode = Artisan::call('recording:convert-mp3', [
                'isCallID' => $isCallId,
            ]);

            if ($exitCode !== 0) {
                throw new \RuntimeException('Failed to convert recording to MP3. The recording may not exist or conversion failed.');
            }

            // Retrieve the MP3 from Redis after conversion
            $mp3Data = Redis::get($cacheKey);

            if (! $mp3Data) {
                throw new \RuntimeException('Recording conversion completed but MP3 data not found');
            }
        }

        return [
            'isCallId' => $isCallId,
            'format' => 'mp3',
            'encoding' => 'base64',
            'data' => base64_encode($mp3Data),
            'sizeBytes' => strlen($mp3Data),
            'cached' => $cached,
        ];
    }
}
