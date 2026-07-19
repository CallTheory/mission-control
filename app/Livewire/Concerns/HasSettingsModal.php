<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * The open/close modal state shared by the System Integrations settings forms
 * (Twilio, Stripe, Mfax, ...), which present their configuration in a dialog.
 *
 * `$isOpen` stays public so existing Blade `wire:click="$toggle('isOpen')"` wiring
 * keeps working; open()/close() are provided for explicit calls.
 */
trait HasSettingsModal
{
    public bool $isOpen = false;

    public function openSettings(): void
    {
        $this->isOpen = true;
    }

    public function closeSettings(): void
    {
        $this->isOpen = false;
    }
}
