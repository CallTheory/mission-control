<?php

namespace App\Livewire\System;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

class Users extends Component
{
    public function render(): View
    {
        $users = User::paginate(100);

        return view('livewire.system.users')->with('users', $users);
    }
}
