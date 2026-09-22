<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PendingFax extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'last_polled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $fax): void {
            // Before spool sources existed the directory was named after the provider, so
            // a row written without one belongs to that provider's legacy source.
            // Defaulting here rather than at each call site means a missed one degrades to
            // the old behaviour instead of storing a null that silently matches nothing —
            // and a null would make abandonTracking() and the submission dedupe skip the
            // row, which is how a fax goes quietly unsent.
            $fax->spool_source_key ??= $fax->fax_provider;
        });
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('delivery_status', 'pending');
    }

    /**
     * How the account reads in the UI and in alert emails: "12345 — Acme Clinic" when we
     * know both, just the number when the name is missing, and null when the account
     * couldn't be resolved at all.
     */
    public function accountLabel(): ?string
    {
        if (blank($this->client_number)) {
            return null;
        }

        return blank($this->client_name)
            ? (string) $this->client_number
            : "{$this->client_number} — {$this->client_name}";
    }
}
