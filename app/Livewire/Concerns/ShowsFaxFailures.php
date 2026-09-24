<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Models\PendingFax;
use App\Services\Faxing\FaxRetry;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Lists the faxes *we* know failed, and lets someone send them again.
 *
 * Distinct from the provider history the pages already show. A fax whose submission
 * failed never reached the provider, so it is absent from that history however far back
 * you look — the failure email was the only evidence it existed. These rows come from
 * pending_faxes, so they cover both the faxes the provider rejected and the ones it never
 * received.
 */
trait ShowsFaxFailures
{
    abstract protected function faxProvider(): string;

    abstract protected function faxSource(): string;

    /**
     * Unresolved failures, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function faxFailures(): array
    {
        return PendingFax::query()
            ->where('fax_provider', $this->faxProvider())
            ->where('spool_source_key', $this->faxSource())
            ->where('delivery_status', 'failed')
            // A fax already put back for another attempt has a fresh row of its own;
            // showing the old failure too would invite sending it a third time.
            ->whereNull('retried_at')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (PendingFax $fax): array => [
                'id' => $fax->id,
                'account' => $fax->accountLabel() ?? 'Unknown',
                'phone' => $fax->phone,
                'file' => $fax->fs_file_name,
                'reason' => $fax->failure_reason,
                // 'submission' never reached the provider and can only be retried from
                // the spool; 'delivery' did, and the provider has its own record.
                'stage' => $fax->failure_stage,
                'failed_at' => $fax->resolved_at?->toIso8601String(),
                'retryable' => $fax->failure_stage === 'submission',
            ])
            ->all();
    }

    public function retryFaxAction(): Action
    {
        return Action::make('retryFax')
            ->label(__('Send Again'))
            ->link()
            ->requiresConfirmation()
            ->modalHeading(__('Send this fax again?'))
            ->modalDescription(__('The fax is put back in the send folder and goes out on the next scan, '
                .'through whichever provider routing currently selects.'))
            ->modalSubmitActionLabel(__('Send again'))
            ->action(function (array $arguments): void {
                $fax = PendingFax::query()
                    ->whereKey($arguments['fax'] ?? null)
                    ->where('fax_provider', $this->faxProvider())
                    ->where('spool_source_key', $this->faxSource())
                    ->first();

                if ($fax === null) {
                    Notification::make()->title(__('That fax is no longer listed.'))->status('warning')->send();

                    return;
                }

                try {
                    app(FaxRetry::class)->retry($fax, $this->failureActor());
                } catch (Throwable $e) {
                    Notification::make()
                        ->title(__('Could not resend :file', ['file' => $fax->fs_file_name]))
                        ->body($e->getMessage())
                        ->status('danger')
                        ->send();

                    return;
                }

                $this->refreshFaxSpoolState();

                Notification::make()
                    ->title(__(':file is queued to send again.', ['file' => $fax->fs_file_name]))
                    ->status('success')
                    ->send();
            });
    }

    private function failureActor(): string
    {
        $user = Auth::user();

        return $user === null ? 'unknown' : "#{$user->id} {$user->email}";
    }
}
