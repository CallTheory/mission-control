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
