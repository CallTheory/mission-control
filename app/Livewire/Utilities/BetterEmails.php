<?php

namespace App\Livewire\Utilities;

use Illuminate\View\View;
use Livewire\Component;
use App\Models\BetterEmails as BetterEmailsModel;
use Livewire\WithPagination;


class BetterEmails extends Component
{
    use WithPagination;

    public int $editingRecord = 0;

    public array $state = [];

    public $listeners = ['saved' => '$refresh'];

    public function editBetterEmail(BetterEmailsModel $betterEmail): void
    {

        $this->state['client_number'] = $betterEmail->client_number;
        $this->state['subject'] = $betterEmail->subject;
        $this->state['title'] = $betterEmail->title;
        $this->state['description'] = $betterEmail->description;
        $this->state['recipients'] = implode("\n",json_decode($betterEmail->recipients));
        $this->state['report_metadata'] = $betterEmail->report_metadata;
        $this->state['message_history'] = $betterEmail->message_history;
        $this->state['theme'] = $betterEmail->theme;
        $this->state['logo'] = $betterEmail->logo;
        $this->state['logo_alt'] = $betterEmail->logo_alt;
        $this->state['logo_link'] = $betterEmail->logo_link;
        $this->state['button_text'] = $betterEmail->button_text;
        $this->state['button_link'] = $betterEmail->button_link;
        $this->editingRecord = $betterEmail->id;

    }

    public function closeEditModal(): void
    {
        $this->state = [];
        $this->editingRecord = 0;
    }

    public function updateBetterEmail(BetterEmailsModel $betterEmail): void
    {

        $this->validate([
            'state.client_number' => 'required',
            'state.title' => 'required|string|max:100',
            'state.description' => 'required|string|max:255',
            'state.recipients' => 'required',
            'state.report_metadata' => 'required|boolean',
            'state.message_history' => 'required|boolean',
            'state.theme' => 'required|string|in:standard',
            'state.subject' => 'required|max:255',
            'state.logo' => 'required|url',
            'state.logo_alt' => 'required|string',
            'state.logo_link' => 'required|url',
            'state.button_text' => 'required|string|max:50',
            'state.button_link' => 'required|string',
        ]);


        $betterEmail->client_number = $this->state['client_number'];
        $betterEmail->subject = $this->state['subject'];
        $betterEmail->title = $this->state['title'];
        $betterEmail->description = $this->state['description'];
        $betterEmail->recipients = json_encode(explode("\n", $this->state['recipients']));
        $betterEmail->report_metadata = $this->state['report_metadata'];
        $betterEmail->message_history =$this->state['message_history'];
        $betterEmail->theme = $this->state['theme'];
        $betterEmail->logo = $this->state['logo'];
        $betterEmail->logo_alt = $this->state['logo_alt'];
        $betterEmail->logo_link = $this->state['logo_link'];
        $betterEmail->button_text = $this->state['button_text'];
        $betterEmail->button_link = $this->state['button_link'];
        $betterEmail->save();
        $this->state = [];
        $this->editingRecord = 0;
        $this->dispatch('saved');
    }


    public function deleteBetterEmail(BetterEmailsModel $betterEmail): void
    {
        $betterEmail->delete();
        $this->dispatch('saved');
    }

    public function render(): View
    {
        $logs = BetterEmailsModel::paginate(25);
        return view('livewire.utilities.better-emails', ['logs' => $logs]);
    }
}
