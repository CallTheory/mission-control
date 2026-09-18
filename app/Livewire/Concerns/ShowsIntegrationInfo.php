<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Filament\Actions\Action;
use Illuminate\Contracts\View\View;

/**
 * The integration tiles that only explain something -- no fields to edit.
 *
 * SendGrid is the one left: its tile documents the inbound-parse webhook URLs. It
 * shares the tile and dialog with the configurable integrations
 * ({@see ConfiguresDataSource}) so the row reads as one set, but its dialog has
 * content instead of a schema and closes rather than saving.
 */
trait ShowsIntegrationInfo
{
    abstract protected function infoHeading(): string;

    /**
     * The dialog body.
     */
    abstract protected function infoContent(): View;

    public function configureAction(): Action
    {
        return Action::make('configure')
            ->label($this->infoHeading())
            ->modalHeading($this->infoHeading())
            ->modalContent(fn () => $this->infoContent())
            // Nothing to submit; the only way out is to close.
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }
}
