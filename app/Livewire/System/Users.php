<?php

namespace App\Livewire\System;

use App\Enums\Capability;
use App\Livewire\Concerns\AuthorizesSystemComponent;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Component;

class Users extends Component
{
    use AuthorizesSystemComponent;

    protected function requiredCapability(): Capability
    {
        return Capability::AdminManageUsers;
    }

    public function render(): View
    {
        $users = User::paginate(100);

        return view('livewire.system.users')->with('users', $users);
    }
}
