<?php

namespace App\Livewire;

use App\Models\InboundEmailRules;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class EmailRule extends Component
{
    public InboundEmailRules|null $r;

    public bool $isOpen = false;

    public array $state = [];

    public bool $deleteRuleModal = false;

    protected array $rules = [
        'state.name' => 'required',
        'state.category' => 'required',
        'state.mergecomm' => 'required_without:state.account|exists:mergecomm_web_triggers,id|nullable',
        'state.account' => 'required_without:state.mergecomm|string|max:255|nullable',
        'state.rules' => 'required',
        'state.enabled' => 'required|boolean',
    ];

    public function deleteRule(): void
    {
        $this->r->forceDelete();
        $this->redirect('/utilities/inbound-email');
    }

    public function addRule(string $field, string $modifier, string $val): void
    {
        $this->state['rules'][$field][$modifier][] = $val;
    }

    public function removeRule($field, $modifier, $key, $val): void
    {
        if ($this->state['rules'][$field][$modifier][$key] == $val) {
            unset($this->state['rules'][$field][$modifier][$key]);
        }
    }

    public function saveRules(): void
    {
        $this->validate();

        $this->r->name = $this->state['name'];
        $this->r->category = $this->state['category'];
        $this->r->rules = json_encode($this->state['rules'], JSON_UNESCAPED_SLASHES);
        $this->r->mergecomm_trigger_id = $this->state['mergecomm'];
        $this->r->account = $this->state['account'];
        $this->r->enabled = $this->state['enabled'] ?? 0;

        $this->r->save();

        $this->dispatch('saved');
        $this->redirect('/utilities/inbound-email');
    }

    #[On('openFreshPanel')]
    public function openFreshPanel(): void
    {
        $this->state = [];
        $this->r = new InboundEmailRules();
        $this->state['name'] = '';
        $this->state['category'] = '';
        $this->state['rules'] = [];
        $this->state['mergecomm'] = null;
        $this->state['account'] = null;
        $this->state['enabled'] = 0;
        $this->isOpen = 1;
    }

    #[On('openPanel')]
    public function openPanel(InboundEmailRules $r): void
    {
        $this->r = $r;
        $this->state['id'] = $this->r->id;
        $this->state['name'] = $this->r->name;
        $this->state['category'] = $this->r->category;
        $this->state['rules'] = json_decode($this->r->rules, true);
        $this->state['mergecomm'] = $this->r->mergecomm_trigger_id;
        $this->state['account'] = $this->r->account;
        $this->state['enabled'] = $this->r->enabled ?? 0;
        $this->isOpen = 1;
    }

    public $listeners = ['openPanel', 'openFreshPanel'];

    public function render(): View
    {
        return view('livewire.email-rule');
    }
}
