<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Enums\ThemePreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * The three-way appearance switch in the account dropdown.
 *
 * The class on <html> is flipped in the browser the moment you click, so this
 * only has to persist the choice -- no reload, unlike the profile form.
 */
class ThemeToggle extends Component
{
    public string $theme = ThemePreference::Light->value;

    public function mount(): void
    {
        $this->theme = ThemePreference::fromStored(Auth::user()?->dark_mode)->value;
    }

    public function setTheme(string $theme): void
    {
        $preference = ThemePreference::tryFrom($theme);

        if (! $preference) {
            return;
        }

        $this->theme = $preference->value;

        $user = Auth::user();
        $user->dark_mode = $preference->value;
        $user->save();
    }

    public function render(): View
    {
        return view('livewire.profile.theme-toggle', [
            'options' => ThemePreference::cases(),
        ]);
    }
}
