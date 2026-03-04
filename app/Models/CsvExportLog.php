<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvExportLog extends Model
{
    use HasFactory;

    protected $table = 'csv_export_logs';

    protected $fillable = [
        'user_id',
        'team_id',
        'filters',
        'result_count',
        'filename',
        'status',
        'error_message',
    ];

    protected $casts = [
        'filters' => 'array',
        'result_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function markAsCompleted(int $resultCount, string $filename): void
    {
        $this->update([
            'status' => 'completed',
            'result_count' => $resultCount,
            'filename' => $filename,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
