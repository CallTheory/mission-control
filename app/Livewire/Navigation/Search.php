<?php

namespace App\Livewire\Navigation;

use App\Models\Stats\Helpers;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Search extends Component
{

    #[Locked]
    public bool $enabled = false;
    public function clearSearchTerm(): void
    {
        Session::forget('searchTerm');
        $this->searchTerm = null;
        $this->redirect('/utilities/call-lookup/');
    }

    public string|null $searchTerm;


    public function mount(): void
    {
        if(Helpers::isSystemFeatureEnabled('call-lookup') && request()->user()->currentTeam->utility_call_lookup && !request()->user()->currentTeam->personal_team){
            $this->enabled = true;
        }

        if($this->enabled){
            if(!Str::startsWith( request()->path(), 'utilities/call-lookup/')){
                Session::forget('searchTerm');
                $this->searchTerm = null;
            }
            else{
                if(!$this->searchTerm){
                    $this->searchTerm = Session::get('searchTerm');
                }
            }
        }

    }

    public function search(): void
    {
        if($this->enabled){
            $this->resetErrorBag();

            if (strlen($this->searchTerm ?? '') === 0) {
                $this->clearSearchTerm();
            }
            elseif(is_numeric($this->searchTerm) && is_integer((int)$this->searchTerm)  && (int)$this->searchTerm !== 0){
                Session::put('searchTerm', $this->searchTerm);
                $this->redirect('/utilities/call-lookup/'.$this->searchTerm ?? '');
            }
            else {
                $this->clearSearchTerm();
            }
        }
    }

    public function render(): View
    {
        return view('livewire.navigation.search');
    }
}
