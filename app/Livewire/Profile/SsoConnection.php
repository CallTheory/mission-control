<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\System\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * "Single Sign-On" section on the profile page.
 *
 * Linking runs the ordinary SAML flow with a marker in the session so the
 * callback attaches the assertion's subject id to *this* account instead of
 * signing someone in -- see SAML2\CallbackController::completeAccountLink().
 *
 * Worth knowing when reading the copy this renders: sign-in matches on the
 * email address in the assertion, not on saml_linked_id, so unlinking records
 * that the connection is gone without blocking a future SSO sign-in.
 */
class SsoConnection extends Component
{
    public ?string $linkedId = null;

    public function mount(): void
    {
        $this->linkedId = Auth::user()?->saml_linked_id;
    }

    public function unlink(): void
    {
        $user = Auth::user();
        $user->saml_linked_id = null;
        $user->save();

        $this->linkedId = null;
        $this->dispatch('saved');
    }

    public function render(): View
    {
        return view('livewire.profile.sso-connection', [
            'enabled' => Settings::first()?->saml2_enabled === 1,
        ]);
    }
}
