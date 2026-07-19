<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Livewire\Concerns\AuthorizesWctpManagement;
use App\Models\EnterpriseHost;
use App\Models\Team;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Str;

class EnterpriseHostManagement extends Component
{
    use AuthorizesWctpManagement;
    use WithPagination;

    public $showCreateModal = false;
    public $showEditModal = false;
    public $editingHost = null;
    
    // Form fields
    public $name = '';
    public $senderID = '';
    public $securityCode = '';
    public $enabled = true;
    public $callback_url = '';
    public $team_id = null;
    public $phoneNumbers = [];
    public $newPhoneNumber = '';
    
    // Search and filters
    public $search = '';
    public $filterEnabled = '';
    public $filterTeam = '';
    
    protected $rules = [
        'name' => 'required|string|max:255',
        'senderID' => 'required|string|max:255',
        'securityCode' => 'required|string|min:8',
        'enabled' => 'boolean',
        'callback_url' => 'nullable|url',
        'phoneNumbers' => 'array',
        'phoneNumbers.*' => 'string|regex:/^[\+]?[1-9]\d{1,14}$/',
    ];

    public function mount()
    {
        $this->authorizeWctpManagement();
    }

    public function render()
    {
        $this->authorizeWctpManagement();

        // Hosts are always scoped to the acting team; the client cannot widen this.
        $query = EnterpriseHost::query()
            ->where('team_id', $this->currentTeamId())
            ->with(['team', 'messages' => function ($q) {
                $q->latest()->limit(5);
            }])
            ->withCount('messages');

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('senderID', 'like', '%' . $this->search . '%');
            });
        }

        // Apply enabled filter
        if ($this->filterEnabled !== '') {
            $query->where('enabled', (bool) $this->filterEnabled);
        }

        $hosts = $query->orderBy('name')->paginate(10);

        // Only the acting team is ever exposed to the management UI.
        $teams = Team::whereKey($this->currentTeamId())->get();

        return view('livewire.utilities.enterprise-host-management', [
            'hosts' => $hosts,
            'teams' => $teams,
        ]);
    }

    public function createHost()
    {
        $this->resetForm();
        $this->showCreateModal = true;
    }

    public function editHost(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        $this->editingHost = $host;
        $this->name = $host->name;
        $this->senderID = $host->senderID;
        $this->securityCode = ''; // Don't show existing encrypted code
        $this->enabled = $host->enabled;
        $this->callback_url = $host->callback_url ?? '';
        $this->team_id = $host->team_id;
        $this->phoneNumbers = $host->phone_numbers ?? [];
        $this->newPhoneNumber = '';
        
        $this->showEditModal = true;
    }

    public function save()
    {
        $this->authorizeWctpManagement();

        $this->validate();

        $data = [
            'name' => $this->name,
            'senderID' => $this->senderID,
            'enabled' => $this->enabled,
            'callback_url' => $this->callback_url ?: null,
            // Ownership is always the acting team — never trust a client-supplied team_id.
            'team_id' => $this->currentTeamId(),
            'phone_numbers' => array_values($this->phoneNumbers), // Ensure it's a sequential array
        ];

        if ($this->editingHost) {
            // Re-authorize the target on write: the bound model must belong to the team.
            $this->authorizeHost($this->editingHost);

            // Only update security code if a new one was provided
            if ($this->securityCode) {
                $data['securityCode'] = $this->securityCode;
            }

            $this->editingHost->update($data);

            session()->flash('message', 'Enterprise Host updated successfully.');
        } else {
            // Validate unique senderID for new hosts
            $this->validate([
                'senderID' => 'unique:enterprise_hosts,senderID',
            ]);
            
            $data['securityCode'] = $this->securityCode;
            
            EnterpriseHost::create($data);
            
            session()->flash('message', 'Enterprise Host created successfully.');
        }

        $this->resetForm();
        $this->showCreateModal = false;
        $this->showEditModal = false;
    }

    public function deleteHost(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        if ($host->messages()->exists()) {
            session()->flash('error', 'Cannot delete host with existing messages. Disable it instead.');
            return;
        }

        $host->delete();
        
        session()->flash('message', 'Enterprise Host deleted successfully.');
    }

    public function toggleEnabled(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        $host->update(['enabled' => !$host->enabled]);
        
        $status = $host->enabled ? 'enabled' : 'disabled';
        session()->flash('message', "Enterprise Host {$status} successfully.");
    }

    public function generateSecurityCode()
    {
        $this->securityCode = Str::random(16);
    }

    public function addPhoneNumber()
    {
        $this->validate(['newPhoneNumber' => 'required|regex:/^[\+]?[1-9]\d{1,14}$/']);
        
        // Normalize the phone number
        $normalized = preg_replace('/\D+/', '', $this->newPhoneNumber);
        if (!str_starts_with($normalized, '1') && strlen($normalized) == 10) {
            $normalized = '1' . $normalized;
        }
        $formatted = '+' . $normalized;
        
        if (!in_array($formatted, $this->phoneNumbers)) {
            $this->phoneNumbers[] = $formatted;
        }
        
        $this->newPhoneNumber = '';
    }
    
    public function removePhoneNumber($index)
    {
        unset($this->phoneNumbers[$index]);
        $this->phoneNumbers = array_values($this->phoneNumbers);
    }
    
    public function resetForm()
    {
        $this->reset([
            'name',
            'senderID',
            'securityCode',
            'enabled',
            'callback_url',
            'team_id',
            'phoneNumbers',
            'newPhoneNumber',
            'editingHost',
        ]);
        
        $this->resetValidation();
    }

    public function viewMessages(EnterpriseHost $host)
    {
        $this->authorizeHost($host);

        return redirect()->route('utilities.wctp-messages', ['host' => $host->id]);
    }

    /**
     * Ensure the bound host belongs to the acting team before any mutation or
     * navigation. Guards against tampered route-model-bound ids.
     */
    protected function authorizeHost(EnterpriseHost $host): void
    {
        $this->authorizeWctpManagement();

        if ((int) $host->team_id !== $this->currentTeamId()) {
            abort(403);
        }
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterEnabled()
    {
        $this->resetPage();
    }

    public function updatedFilterTeam()
    {
        $this->resetPage();
    }
}