<?php

namespace App\Livewire\Utilities;

use App\Models\Stats\Calls\Call;
use Exception;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class CallLookupModal extends ModalComponent
{
    public ?int $isCallID = null;

    public array $state = [];

    /**
     * @throws Exception
     */
    public function lookupCall(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'isCallID' => 'required|numeric|between:1,10000000000',
        ], [
            'isCallID' => $this->isCallID ?? null,
        ]);

        try {
            $call = new Call(['ISCallId' => $this->isCallID]);
        } catch (Exception $e) {
            $this->addError('isCallID', $e->getMessage());

            return;
        }

        $this->state['details'] = $call->details();
        $this->state['messages'] = $call->messages();
        $this->state['history'] = $call->history();
        $this->state['statistics'] = $call->statistics();
        $this->state['recordings'] = $call->recordings();
        $this->state['agents'] = $call->agents();
        $this->state['clients'] = $call->clients();
        $this->state['tracker'] = $call->tracker();

        $this->dispatch('search');
    }

    /**
     * @throws Exception
     */
    public function mount(?int $isCallID = null): void
    {
        if ($this->isCallID) {
            $this->lookupCall();
        } else {
            $this->clear();
        }
    }

    protected function clear(): void
    {
        $this->state['details'] = null;
        $this->state['messages'] = null;
        $this->state['history'] = null;
        $this->state['statistics'] = null;
        $this->state['recordings'] = null;
        $this->state['agents'] = null;
        $this->state['clients'] = null;
        $this->state['tracker'] = null;
    }

    public function render(): View
    {
        return view('livewire.utilities.call-lookup-modal');
    }

    public static function modalMaxWidth(): string
    {
        return '7xl';
    }
}
