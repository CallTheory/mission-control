<?php

namespace App\Livewire\System;

use App\Models\System\Settings;
use Illuminate\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BetterEmails extends Component
{
    public string $theme = 'standard';

    public string $preview_url;

    public string $message_history;

    public string $report_metadata;

    public string $title;

    public string $description;

    public string $subject;

    public string $logo;

    public string $logo_alt;

    public string $logo_link;

    public string $button_text;

    public string $button_link;

    public string $canspam_address;

    public string $canspam_address2;

    public string $canspam_city;

    public string $canspam_state;

    public string $canspam_postal;

    public string $canspam_country;

    public string $canspam_email;

    public string $canspam_phone;

    public string $canspam_company;

    public string $example_file;

    public function updatePreview(): void
    {
        $qs = "message_history={$this->message_history}";
        $qs .= "&report_metadata={$this->report_metadata}";
        $qs .= "&title={$this->title}";
        $qs .= "&description={$this->description}";
        $qs .= "&logo={$this->logo}";
        $qs .= "&logo_alt={$this->logo_alt}";
        $qs .= "&logo_link={$this->logo_link}";
        $qs .= "&button_text={$this->button_text}";
        $qs .= "&button_link={$this->button_link}";
        $qs .= "&example_file={$this->example_file}";
        $qs .= "&canspam_address={$this->canspam_address}";
        $qs .= "&canspam_address2={$this->canspam_address2}";
        $qs .= "&canspam_city={$this->canspam_city}";
        $qs .= "&canspam_state={$this->canspam_state}";
        $qs .= "&canspam_postal={$this->canspam_postal}";
        $qs .= "&canspam_country={$this->canspam_country}";
        $qs .= "&canspam_email={$this->canspam_email}";
        $qs .= "&canspam_phone={$this->canspam_phone}";
        $qs .= "&canspam_company={$this->canspam_company}";
        $this->preview_url = "/system/better-emails/preview/{$this->theme}?{$qs}";
    }

    public function downloadThemeTemplate(): BinaryFileResponse
    {
        $theme = resource_path('views/emails/better-emails/theme_template.html');

        return response()->download($theme, 'mission_control_theme_template.html');
    }

    public function saveDefaultSettings(): void
    {
        $settings = Settings::first();
        $settings->better_emails_message_history = $this->message_history;
        $settings->better_emails_report_metadata = $this->report_metadata;
        $settings->better_emails_title = $this->title;
        $settings->better_emails_description = $this->description;
        $settings->better_emails_subject = $this->subject;
        $settings->better_emails_logo = $this->logo;
        $settings->better_emails_logo_alt = $this->logo_alt;
        $settings->better_emails_logo_link = $this->logo_link;
        $settings->better_emails_button_text = $this->button_text;
        $settings->better_emails_button_link = $this->button_link;
        $settings->better_emails_theme = $this->theme;
        $settings->better_emails_canspam_address = $this->canspam_address;
        $settings->better_emails_canspam_address2 = $this->canspam_address2;
        $settings->better_emails_canspam_city = $this->canspam_city;
        $settings->better_emails_canspam_state = $this->canspam_state;
        $settings->better_emails_canspam_postal = $this->canspam_postal;
        $settings->better_emails_canspam_country = $this->canspam_country;
        $settings->better_emails_canspam_email = $this->canspam_email;
        $settings->better_emails_canspam_phone = $this->canspam_phone;
        $settings->better_emails_canspam_company = $this->canspam_company;
        $settings->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {

        $settings = Settings::first();
        $this->message_history = $settings->better_emails_message_history ?? false;
        $this->report_metadata = $settings->better_emails_report_metadata ?? false;
        $this->title = $settings->better_emails_title ?? 'Title';
        $this->description = $settings->better_emails_description ?? 'Description';
        $this->subject = $settings->better_emails_subject ?? 'Subject';
        $this->logo = $settings->better_emails_logo ?? '/images/mission-control.png';
        $this->logo_alt = $settings->better_emails_logo_alt ?? 'Logo';
        $this->logo_link = $settings->better_emails_logo_link ?? 'https://example.com';
        $this->button_text = $settings->better_emails_button_text ?? 'Contact Support';
        $this->button_link = $settings->better_emails_button_link ?? 'mailto:support@example.com';
        $this->theme = $settings->better_emails_theme ?? 'standard';
        $this->canspam_address = $settings->better_emails_canspam_address ?? '123 Main Street';
        $this->canspam_address2 = $settings->better_emails_canspam_address2 ?? '';
        $this->canspam_city = $settings->better_emails_canspam_city ?? 'Grove City';
        $this->canspam_state = $settings->better_emails_canspam_state ?? 'OH';
        $this->canspam_postal = $settings->better_emails_canspam_postal ?? '43123';
        $this->canspam_country = $settings->better_emails_canspam_country ?? 'US';
        $this->canspam_email = $settings->better_emails_canspam_email ?? 'support@example.com';
        $this->canspam_phone = $settings->better_emails_canspam_phone ?? '1-555-555-5555';
        $this->canspam_company = $settings->better_emails_canspam_company ?? 'Example Company';
        $this->example_file = 'messages 5520 06112024-070001.txt';
    }

    public function render(): View
    {
        $this->updatePreview();

        return view('livewire.system.better-emails');
    }
}
