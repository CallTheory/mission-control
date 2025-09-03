<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\DataSource;
use App\Services\WctpService;
use App\Services\TwilioService;
use Illuminate\View\View;
use Livewire\Component;
use Exception;

class WctpGateway extends Component
{
    public string $testMessage = '';
    public string $testRecipient = '';
    public string $testResult = '';
    public bool $showTestPanel = false;
    public bool $twilioConfigured = false;
    public string $wctpEndpoint = '';

    public function mount(): void
    {
        // Check if Twilio is configured in DataSource only
        try {
            $dataSource = DataSource::first();
            $this->twilioConfigured = $dataSource 
                && !empty($dataSource->twilio_account_sid) 
                && !empty($dataSource->twilio_auth_token)
                && !empty($dataSource->twilio_from_number);
        } catch (Exception $e) {
            $this->twilioConfigured = false;
        }
            
        // Set the WCTP endpoint URL (now at root /wctp)
        $this->wctpEndpoint = url('/wctp');
    }

    public function sendTestMessage(): void
    {
        $this->validate([
            'testRecipient' => 'required|regex:/^[0-9]{10,15}$/',
            'testMessage' => 'required|min:1|max:160'
        ]);

        try {
            // Create a test WCTP submit request
            $wctpXml = $this->buildTestWctpMessage();
            
            // Send directly via Twilio for testing
            $twilioService = new TwilioService();
            $result = $twilioService->sendSms($this->testRecipient, $this->testMessage);
            
            if ($result['success']) {
                $this->testResult = "✓ Test message sent successfully! Message SID: " . $result['message_sid'];
            } else {
                $this->testResult = "✗ Failed to send test message: " . $result['error'];
            }
        } catch (Exception $e) {
            $this->testResult = "✗ Error: " . $e->getMessage();
        }
    }

    protected function buildTestWctpMessage(): string
    {
        $wctpService = new WctpService();
        return $wctpService->createSubmitRequest(
            'TestSender',
            $this->testRecipient,
            $this->testMessage,
            uniqid('test_')
        );
    }

    public function toggleTestPanel(): void
    {
        $this->showTestPanel = !$this->showTestPanel;
        if (!$this->showTestPanel) {
            $this->reset(['testMessage', 'testRecipient', 'testResult']);
        }
    }

    public function render(): View
    {
        return view('livewire.utilities.wctp-gateway');
    }
}
