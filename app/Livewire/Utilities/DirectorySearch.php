<?php

namespace App\Livewire\Utilities;

use App\Models\Stats\Subjects\Listings\ContactCisco;
use App\Models\Stats\Subjects\Listings\ContactEmail;
use App\Models\Stats\Subjects\Listings\ContactFax;
use App\Models\Stats\Subjects\Listings\ContactPhone;
use App\Models\Stats\Subjects\Listings\ContactSecureMessaging;
use App\Models\Stats\Subjects\Listings\ContactSms;
use App\Models\Stats\Subjects\Listings\ContactTapPager;
use App\Models\Stats\Subjects\Listings\ContactVocera;
use App\Models\Stats\Subjects\Listings\ContactWctp;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class DirectorySearch extends Component
{
    public mixed $searchResults = null;

    public string $searchQuery = '';

    public string $contactSearchType = 'phone';

    public function searchDirectoryContacts(): void
    {
        $this->validate([
            'searchQuery' => 'required|string|min:3|max:255',
            'contactSearchType' => 'required|string|in:phone,email,fax,vocera,cisco,wctp,tap,msm,sms',
        ]);
        $results = null;
        try {
            switch ($this->contactSearchType) {
                case 'phone':
                    $results = new ContactPhone([0 => $this->searchQuery]);
                    break;
                case 'email':
                    $results = new ContactEmail([0 => $this->searchQuery]);
                    break;
                case 'fax':
                    $results = new ContactFax([0 => $this->searchQuery]);
                    break;
                case 'vocera':
                    $results = new ContactVocera([0 => $this->searchQuery]);
                    break;
                case 'cisco':
                    $results = new ContactCisco([0 => $this->searchQuery]);
                    break;
                case 'wctp':
                    $results = new ContactWctp([0 => $this->searchQuery]);
                    break;
                case 'tap':
                    $results = new ContactTapPager([0 => $this->searchQuery]);
                    break;
                case 'msm':
                    $results = new ContactSecureMessaging([0 => $this->searchQuery]);
                    break;
                case 'sms':
                    $results = new ContactSms([0 => $this->searchQuery]);
                    break;
            }
        } catch (Exception $e) {
            $results = [];
        }

        if (is_null($results)) {
            $this->searchResults = [];
        } else {
            $this->searchResults = $results->results ?? [];

        }
    }

    public function render(): View
    {
        return view('livewire.utilities.directory-search');
    }
}
