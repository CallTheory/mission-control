<?php

namespace App\Livewire\Teams;

use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditUserProfile extends Component
{
    public bool $editingProfile = false;

    public array $state;

    #[Locked]
    public int $user_id;

    public function updateProfile(): void
    {
        $this->validate([
            'state.name' => 'required|string|max:255',
            'state.email' => 'required|string|email|max:255',
            'state.agtId' => 'nullable|int',
        ]);

        $user = User::find($this->user_id);
        $user->name = $this->state['name'];
        $user->email = $this->state['email'];
        $user->agtId = strlen($this->state['agtId'] ?? '') > 0 ? $this->state['agtId'] : null;
        $user->save();

        $this->editingProfile = false;
    }

    public function mount($user): void
    {
        $this->user_id = $user->id;
        $this->state['name'] = $user->name;
        $this->state['email'] = $user->email;
        $this->state['agtId'] = $user->agtId;
    }

    public function render(): View
    {
        return view('livewire.teams.edit-user-profile');
    }
}
