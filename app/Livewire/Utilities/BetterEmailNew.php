<?php

namespace App\Livewire\Utilities;

use App\Models\BetterEmails as BetterEmailsModel;
use App\Models\System\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Component;
use Mockery\Exception;

class BetterEmailNew extends Component
{
    public bool $isOpen = false;
    public array $state;

    public function addBetterEmail():void
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

        $bem = new BetterEmailsModel();
        $bem->client_number = $this->state['client_number'];
        $bem->title = $this->state['title'];
        $bem->description = $this->state['description'];
        $bem->recipients = json_encode(explode("\n", $this->state['recipients']));
        $bem->report_metadata = $this->state['report_metadata'];
        $bem->message_history = $this->state['message_history'];
        $bem->theme = $this->state['theme'];
        $bem->subject = $this->state['subject'];
        $bem->logo = $this->state['logo'];
        $bem->logo_alt = $this->state['logo_alt'];
        $bem->logo_link = $this->state['logo_link'];
        $bem->button_text = $this->state['button_text'];
        $bem->button_link = $this->state['button_link'];

        $saved = true;
        try{
            $bem->save();
        }
        catch(Exception $e){
            $this->dispatch('saved', 'There was an error saving the better email');
            $saved = false;
        }

        if($saved){
            Storage::makeDirectory("better-emails/{$bem->client_number}/{$bem->id}");
            $this->isOpen = false;
            $this->clearFields();
            $this->dispatch('saved');
        }

    }

    protected function clearFields(): void
    {
        $this->state['client_number'] = '';
        $this->state['title'] = '';
        $this->state['description'] = '';
        $this->state['recipients'] = '';
        $this->state['report_metadata'] = 0;
        $this->state['message_history'] = 1;
        $this->state['theme'] = 'standard';
        $this->state['subject'] = '';
        $this->state['logo'] = '';
        $this->state['logo_alt'] = '';
        $this->state['logo_link'] = '';
        $this->state['button_text'] = '';
        $this->state['button_link'] = '';
    }

    public function mount(): void
    {
        $this->loadDefault();
    }

    public function loadDefault(): void
    {
        $settings = Settings::first();
        $this->state['title'] = $settings->better_emails_title;
        $this->state['description'] = $settings->better_emails_description;
        $this->state['report_metadata'] = $settings->better_emails_report_metadata;
        $this->state['message_history'] = $settings->better_emails_message_history;
        $this->state['theme'] = $settings->better_emails_theme;
        $this->state['subject'] = $settings->better_emails_subject;
        $this->state['logo'] = $settings->better_emails_logo;
        $this->state['logo_alt'] = $settings->better_emails_logo_alt;
        $this->state['logo_link'] = $settings->better_emails_logo_link;
        $this->state['button_text'] = $settings->better_emails_button_text;
        $this->state['button_link'] = $settings->better_emails_button_link;
    }

    public function render(): View
    {
        return view('livewire.utilities.better-email-new');
    }
}
