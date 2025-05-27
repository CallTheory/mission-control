<?php

namespace App\Livewire\System;

use App\Models\System\Settings;
use Illuminate\View\View;
use Livewire\Component;

class BoardCheck extends Component
{
    public array $state;

    public Settings $settings;

    public function saveBoardCheckSettings(): void
    {
        $this->settings->board_check_starting_msgId = $this->state['board_check_starting_msgId'];
        $this->settings->board_check_people_praise_export_method = $this->state['board_check_people_praise_export_method'];
        $this->settings->save();
        $this->dispatch('saved');
    }

    public function mount(): void
    {
        $this->settings = Settings::firstOrFail();
        $this->state['board_check_starting_msgId'] = $this->settings->board_check_starting_msgId;
        $this->state['board_check_people_praise_export_method'] = $this->settings->board_check_people_praise_export_method;
    }

    public function render(): View
    {
        return view('livewire.system.board-check');
    }
}
