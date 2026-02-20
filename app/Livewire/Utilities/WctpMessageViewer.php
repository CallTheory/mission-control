<?php

declare(strict_types=1);

namespace App\Livewire\Utilities;

use App\Models\WctpMessage;
use App\Models\EnterpriseHost;
use Livewire\Component;
use Livewire\WithPagination;

class WctpMessageViewer extends Component
{
    use WithPagination;

    public $host = null;
    public $search = '';
    public $filterStatus = '';
    public $filterDirection = '';
    public $filterCarrier = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $selectedMessage = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStatus' => ['except' => ''],
        'filterDirection' => ['except' => ''],
        'filterCarrier' => ['except' => ''],
        'host' => ['except' => null],
    ];

    public function mount()
    {
        // Permission check removed - add back if needed
        // if (!auth()->user()->can('manage-wctp')) {
        //     abort(403, 'Unauthorized');
        // }

        if (request()->has('host')) {
            $this->host = request()->get('host');
        }
    }

    public function render()
    {
        $query = WctpMessage::query()
            ->with(['enterpriseHost']);

        // Filter by host if specified
        if ($this->host) {
            $query->where('enterprise_host_id', $this->host);
        }

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('to', 'like', '%' . $this->search . '%')
                    ->orWhere('from', 'like', '%' . $this->search . '%')
                    ->orWhere('wctp_message_id', 'like', '%' . $this->search . '%')
                    ->orWhere('twilio_sid', 'like', '%' . $this->search . '%');
            });
        }

        // Apply status filter
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        // Apply direction filter
        if ($this->filterDirection) {
            $query->where('direction', $this->filterDirection);
        }

        // Carrier filter not applicable in current implementation
        // All messages are sent via Twilio
        if ($this->filterCarrier && $this->filterCarrier === 'twilio') {
            // No need to filter as all messages are Twilio
        }

        // Apply date filters
        if ($this->dateFrom) {
            $query->where('created_at', '>=', $this->dateFrom . ' 00:00:00');
        }
        if ($this->dateTo) {
            $query->where('created_at', '<=', $this->dateTo . ' 23:59:59');
        }

        $messages = $query->orderBy('created_at', 'desc')->paginate(20);

        // Get available hosts for filter
        $hosts = EnterpriseHost::orderBy('name')->get();

        // All messages use Twilio as carrier in current implementation
        $carriers = collect(['twilio']);

        return view('livewire.utilities.wctp-message-viewer', [
            'messages' => $messages,
            'hosts' => $hosts,
            'carriers' => $carriers,
        ]);
    }

    public function viewMessage(WctpMessage $message)
    {
        $this->selectedMessage = $message;
    }

    public function closeMessageModal()
    {
        $this->selectedMessage = null;
    }

    public function retryMessage(WctpMessage $message)
    {
        if ($message->status === 'failed') {
            $message->update(['status' => 'pending', 'failed_at' => null]);
            \App\Jobs\ProcessWctpMessage::dispatch($message);
            
            session()->flash('message', 'Message queued for retry.');
        }
    }

    public function exportMessages()
    {
        // TODO: Implement CSV export
        session()->flash('message', 'Export feature coming soon.');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function updatingFilterDirection()
    {
        $this->resetPage();
    }

    public function updatingFilterCarrier()
    {
        $this->resetPage();
    }

    public function updatingHost()
    {
        $this->resetPage();
    }
}