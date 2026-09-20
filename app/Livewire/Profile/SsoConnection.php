<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\System\Settings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
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

    /**
     * Unlink, then hand the account a way back in.
     *
     * Every SAML sign-in rotates the stored password to random bytes, so an
     * account that has only ever used SSO has no password its owner knows. Just
     * clearing the link would strand anyone without an agtId to fall back on,
     * so a reset link goes out in the same breath.
     *
     * Other sessions are dropped at the same time: the link is being severed,
     * and a session opened under it should not outlive that.
     */
    public function unlink(): void
    {
        $user = Auth::user();
        $user->saml_linked_id = null;
        $user->save();

        Password::sendResetLink(['email' => $user->email]);

        $this->logOutOtherSessions($user->getAuthIdentifier());

        $this->linkedId = null;
        $this->dispatch('saved');
    }

    /**
     * Mirrors Jetstream's LogoutOtherBrowserSessionsForm, which cannot be used
     * directly here: its logoutOtherDevices() needs the plaintext password, and
     * unlinking never asks for one.
     */
    private function logOutOtherSessions(int|string $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $userId);

        // Keep the caller signed in where there is a session to keep. A request
        // without a session store has nothing to preserve, and asking it for an
        // id throws, so every row goes in that case.
        if (request()->hasSession()) {
            $query->where('id', '!=', request()->session()->getId());
        }

        $query->delete();
    }

    public function render(): View
    {
        return view('livewire.profile.sso-connection', [
            'enabled' => Settings::first()?->saml2_enabled === 1,
        ]);
    }
}
