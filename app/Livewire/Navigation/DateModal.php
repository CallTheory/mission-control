<?php

namespace App\Livewire\Navigation;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use LivewireUI\Modal\ModalComponent;

class DateModal extends ModalComponent
{
    public string $start_date;

    public string $end_date;

    public function mount(): void
    {
        $this->start_date = Session::get('start_date') ?? Carbon::now(Auth::user()->timezone ?? 'UTC')->subHours(8)->format('Y-m-d H:i:s');
        $this->end_date = Session::get('end_date') ?? Carbon::now(Auth::user()->timezone ?? 'UTC')->format('Y-m-d H:i:s');
    }

    public function update(): void
    {
        Session::put('start_date', $this->start_date);
        Session::put('end_date', $this->end_date);
        $this->dispatch('closeModal');
    }

    public function render(): View
    {
        return view('livewire.navigation.date-modal');
    }
}
