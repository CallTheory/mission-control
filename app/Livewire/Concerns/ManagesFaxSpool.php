<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Enums\Capability;
use App\Services\Faxing\FaxSpool;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Lets a fax utility page delete stuck files out of the spool directories.
 *
 * A phantom .cap or .fs left in a spool folder stalls a client's faxing and previously
 * needed an SSH session to clear. The alerting already names the file; this puts the
 * removal in the same place, behind Capability::FaxManageSpool.
 *
 * Every action re-authorizes inside its own closure. Filament's `visible()` controls
 * whether a button is drawn, which is presentation — a Livewire action can be invoked
 * by anyone who can reach POST /livewire/update, so the gate has to be checked where
 * the deletion actually happens.
 */
trait ManagesFaxSpool
{
    /**
     * Which provider's spool this component manages ('mfax' or 'ringcentral').
     */
    abstract protected function faxProvider(): string;

    /**
     * Re-read the folder listings after a mutation, so the page reflects the deletion
     * immediately rather than waiting for the next poll.
     */
    abstract protected function refreshFaxSpoolState(): void;

    public function canManageFaxSpool(): bool
    {
        return Gate::allows(Capability::FaxManageSpool->value);
    }

    public function deleteSpoolFileAction(): Action
    {
        return Action::make('deleteSpoolFile')
            ->label(__('Delete'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('Delete :file?', ['file' => $arguments['file'] ?? '']))
            ->modalDescription(__('The file is removed from the spool directory permanently. Amtelco\'s fax service will never process it, and it cannot be recovered from here.'))
            ->modalSubmitActionLabel(__('Delete file'))
            ->visible(fn (): bool => $this->canManageFaxSpool())
            ->action(function (array $arguments): void {
                Gate::authorize(Capability::FaxManageSpool->value);

                $file = (string) ($arguments['file'] ?? '');
                $folder = (string) ($arguments['folder'] ?? '');

                try {
                    $deleted = (new FaxSpool)->delete(
                        $this->faxProvider(),
                        $folder,
                        $file,
                        $this->spoolActor()
                    );
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('Could not delete :file', ['file' => $file]))
                        ->body($e->getMessage())
                        ->status('danger')
                        ->send();

                    return;
                }

                $this->refreshFaxSpoolState();

                Notification::make()
                    ->title($deleted
                        ? __(':file deleted.', ['file' => $file])
                        : __(':file was already gone.', ['file' => $file]))
                    ->status($deleted ? 'success' : 'warning')
                    ->send();
            });
    }

    public function clearSpoolFolderAction(): Action
    {
        return Action::make('clearSpoolFolder')
            ->label(__('Clear Folder'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => __('Clear the :folder folder?', [
                'folder' => FaxSpool::folderLabel((string) ($arguments['folder'] ?? '')),
            ]))
            ->modalDescription(__('Every file in this folder is deleted permanently. Any fax still waiting here will never be sent.'))
            ->modalSubmitActionLabel(__('Delete all files'))
            ->visible(fn (): bool => $this->canManageFaxSpool())
            ->action(function (array $arguments): void {
                Gate::authorize(Capability::FaxManageSpool->value);

                $folder = (string) ($arguments['folder'] ?? '');

                try {
                    $deleted = (new FaxSpool)->clear($this->faxProvider(), $folder, $this->spoolActor());
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('Could not clear the folder'))
                        ->body($e->getMessage())
                        ->status('danger')
                        ->send();

                    return;
                }

                $this->refreshFaxSpoolState();

                Notification::make()
                    ->title(trans_choice('Deleted :count file.|Deleted :count files.', $deleted, ['count' => $deleted]))
                    ->status($deleted > 0 ? 'success' : 'warning')
                    ->send();
            });
    }

    /**
     * Who to record against the deletion in the log.
     */
    private function spoolActor(): string
    {
        $user = Auth::user();

        return $user === null ? 'unknown' : "#{$user->id} {$user->email}";
    }
}
