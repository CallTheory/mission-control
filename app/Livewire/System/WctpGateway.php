<?php

declare(strict_types=1);

namespace App\Livewire\System;

use App\Models\EnterpriseHost;
use App\Models\DataSource;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class WctpGateway extends Component
{
    // Enterprise Hosts
    public Collection $enterpriseHosts;

    // New Enterprise Host form
    public array $newHost = [
        'name' => '',
        'senderID' => '',
        'securityCode' => '',
        'callback_url' => '',
    ];

    // Phone Number Management for hosts
    public array $hostPhoneNumbers = [];

    // UI State
    public string $activeTab = 'overview';
    public bool $twilioConfigured = false;
    public string $wctpEndpoint = '';

    public function mount(): void
    {
        // Check if Twilio is configured
        try {
            $dataSource = DataSource::where('type', 'twilio')
                ->where('enabled', true)
                ->first();
            
            $this->twilioConfigured = $dataSource 
                && !empty($dataSource->twilio_account_sid) 
                && !empty($dataSource->twilio_auth_token)
                && !empty($dataSource->twilio_from_number);
        } catch (\Exception $e) {
            $this->twilioConfigured = false;
        }

        // Set the WCTP endpoint URL
        $this->wctpEndpoint = url('/wctp');

        $this->loadEnterpriseHosts();
    }

    protected function loadEnterpriseHosts(): void
    {
        $teamId = auth()->user()->currentTeam->id ?? null;
        
        $query = EnterpriseHost::query();
        
        if ($teamId) {
            $query->where(function($q) use ($teamId) {
                $q->where('team_id', $teamId)
                  ->orWhereNull('team_id'); // Include global hosts
            });
        }
        
        $this->enterpriseHosts = $query->orderBy('name')->get();

        // Load phone number assignments
        foreach ($this->enterpriseHosts as $host) {
            $this->hostPhoneNumbers[$host->id] = $host->phone_numbers
                ? implode(', ', $host->phone_numbers)
                : '';
        }
    }

    public function addEnterpriseHost(): void
    {
        $this->validate([
            'newHost.name' => 'required|string|max:255',
            'newHost.senderID' => 'required|string|max:255|unique:enterprise_hosts,senderID',
            'newHost.securityCode' => 'required|string|min:8|max:255',
            'newHost.callback_url' => 'nullable|url|max:255',
        ], [
            'newHost.name.required' => 'Name is required',
            'newHost.senderID.required' => 'Sender ID is required',
            'newHost.senderID.unique' => 'This Sender ID is already in use',
            'newHost.securityCode.required' => 'Security Code is required',
            'newHost.securityCode.min' => 'Security Code must be at least 8 characters',
            'newHost.callback_url.url' => 'Callback URL must be a valid URL',
        ]);

        EnterpriseHost::create([
            'team_id' => auth()->user()->currentTeam->id ?? null,
            'name' => $this->newHost['name'],
            'senderID' => $this->newHost['senderID'],
            'securityCode' => $this->newHost['securityCode'],
            'callback_url' => $this->newHost['callback_url'] ?: null,
            'phone_numbers' => [],
            'enabled' => true,
        ]);

        // Reset form
        $this->newHost = [
            'name' => '',
            'senderID' => '',
            'securityCode' => '',
            'callback_url' => '',
        ];

        $this->loadEnterpriseHosts();
        session()->flash('message', 'Enterprise Host added successfully. Configure phone numbers in the Enterprise Host Management page.');
    }

    public function toggleEnterpriseHost(int $hostId): void
    {
        $host = $this->findHost($hostId);
        
        if ($host) {
            $host->update(['enabled' => !$host->enabled]);
            $this->loadEnterpriseHosts();
            session()->flash('message', 'Enterprise Host ' . ($host->enabled ? 'enabled' : 'disabled'));
        }
    }

    public function removeEnterpriseHost(int $hostId): void
    {
        $host = $this->findHost($hostId);
        
        if ($host) {
            // Check if host has messages
            if ($host->messages()->exists()) {
                session()->flash('error', 'Cannot delete host with existing messages. Disable it instead.');
                return;
            }
            
            $hostName = $host->name;
            $host->delete();
            
            $this->loadEnterpriseHosts();
            session()->flash('message', "Enterprise Host '{$hostName}' removed");
        }
    }

    public function updateHostPhoneNumbers(int $hostId): void
    {
        $host = $this->findHost($hostId);
        
        if ($host) {
            $numbersString = $this->hostPhoneNumbers[$hostId] ?? '';
            $numbers = [];

            if ($numbersString) {
                // Parse comma-separated numbers and clean them
                $parts = explode(',', $numbersString);
                foreach ($parts as $number) {
                    $cleaned = preg_replace('/\D+/', '', trim($number));
                    if ($cleaned) {
                        // Ensure proper format with country code
                        if (!str_starts_with($cleaned, '1') && strlen($cleaned) == 10) {
                            $cleaned = '1' . $cleaned;
                        }
                        $numbers[] = '+' . $cleaned;
                    }
                }
            }

            $host->update(['phone_numbers' => array_unique($numbers) ?: []]);
            $this->loadEnterpriseHosts();

            session()->flash('message', "Phone numbers updated for {$host->name}");
        }
    }

    protected function findHost(int $hostId): ?EnterpriseHost
    {
        $teamId = auth()->user()->currentTeam->id ?? null;
        
        $query = EnterpriseHost::where('id', $hostId);
        
        if ($teamId) {
            $query->where(function($q) use ($teamId) {
                $q->where('team_id', $teamId)
                  ->orWhereNull('team_id');
            });
        }
        
        return $query->first();
    }

    public function generateSecurityCode(): void
    {
        $this->newHost['securityCode'] = \Illuminate\Support\Str::random(16);
    }

    public function render(): View
    {
        return view('livewire.system.wctp-gateway');
    }
}