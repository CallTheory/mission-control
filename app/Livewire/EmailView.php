<?php

namespace App\Livewire;

use App\Jobs\InboundRuleMatch;
use App\Mail\ForwardInboundEmail;
use App\Models\InboundEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Component;

class EmailView extends Component
{
    public InboundEmail $email;

    public bool $isOpen = false;

    public array $state = [];

    public bool $deleteEmailModal = false;

    public bool $forwardEmailModal = false;

    public bool $processEmailModal = false;

    public function render(): View
    {
        return view('livewire.email-view');
    }

    public function forwardEmail(): void
    {
        $this->forwardEmailModal = false;
        Mail::send(new ForwardInboundEmail($this->email, $this->state['forward_email']));
        $this->state['forward_email'] = '';
        $this->dispatch('forwarded');
    }

    public function processEmail(): void
    {
        $this->email->ignored_at = null;
        $this->email->processed_at = null;
        $this->email->save();
        InboundRuleMatch::dispatch($this->email);
        $this->redirect('/utilities/inbound-email');
    }

    public function deleteEmail(): void
    {
        $this->email->forceDelete();
        $this->redirect('/utilities/inbound-email');
    }

    public function openEmailPanel(InboundEmail $email): void
    {
        $this->email = $email;
        $this->isOpen = ! $this->isOpen;
        $this->state['subject'] = $this->email->subject;
        $this->state['to'] = $this->email->to;
        $this->state['from'] = $this->email->from;
        $this->state['text'] = $this->email->text;
        $this->state['category'] = $this->email->category;
        $this->state['created_at'] = $this->email->created_at->timezone(Auth::user()->timezone)->format('m/d/Y g:i:s A T');
        $this->state['forward_email'] = '';

    }

    public $listeners = ['openEmailPanel'];
}
