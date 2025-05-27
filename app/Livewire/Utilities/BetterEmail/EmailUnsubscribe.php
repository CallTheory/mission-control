<?php

namespace App\Livewire\Utilities\BetterEmail;

use App\Models\System\Settings;
use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Livewire\Component;
use App\Mail\BetterEmailsUnsubscribeNotification;

class EmailUnsubscribe extends Component
{

    public bool $unsubscribed = false;

    public $email;
    public $campaign;

    public array $company_details;

    public function mount($email, $campaign): void
    {
        $this->email = $email;
        $this->campaign = $campaign;
        if(!in_array($this->email, json_decode($this->campaign->recipients))){
            abort(404);
        }

        $settings = Settings::firstOrFail();
        $this->company_details = [
            'company' => $settings->better_emails_canspam_company,
            'address' => $settings->better_emails_canspam_address,
            'address2' => $settings->better_emails_canspam_address2,
            'city' => $settings->better_emails_canspam_city,
            'state' => $settings->better_emails_canspam_state,
            'postal' => $settings->better_emails_canspam_postal,
            'country' => $settings->better_emails_canspam_country,
            'phone' => $settings->better_emails_canspam_phone,
            'email' => $settings->better_emails_canspam_email,
        ];
    }

    public function unsubscribe(): void
    {
        $settings = Settings::firstOrFail();

        $recipientList = json_decode($this->campaign->recipients);
        $updatedList = [];
        foreach($recipientList as $key => $email){
            if($email != $this->email){
                $updatedList[] = $email;
            }
        }

        if(count($updatedList) == 0){
            $updatedList[] = $settings->better_emails_canspam_email;
        }

        $this->campaign->recipients = json_encode($updatedList);
        $notes = '';
        try{
            $this->campaign->save();
        }catch(Exception $e){
            $notes = "Unable to remove email from campaign.  Error: {$e->getMessage()}";
        }

        Mail::to($settings->better_emails_canspam_email)->queue(new BetterEmailsUnsubscribeNotification([
            'unsubscribeEmail' => $this->email,
            'accountNumber' => $this->campaign->client_number,
            'unsubscribeTitle' => $this->campaign->title,
            'additionalNotes' => $notes,
        ]));

        $this->dispatch('unsubscribed');
        $this->unsubscribed = true;
    }

    public function render(): View
    {
        return view('livewire.utilities.better-email.email-unsubscribe', [
            'email' => $this->email,
            'campaign' => $this->campaign
        ]);
    }
}
