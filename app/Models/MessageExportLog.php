<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageExportLog extends Model
{
    use HasFactory;

    protected $table = 'message_export_logs';

    protected $fillable = [
        'message_export_id',
        'team_id',
        'user_id',
        'export_name',
        'client_number',
        'start_date',
        'end_date',
        'message_count',
        'status',
        'error_message',
        'file_path',
        'sent_at',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'sent_at' => 'datetime',
        'message_count' => 'integer',
    ];

    public function messageExport(): BelongsTo
    {
        return $this->belongsTo(MessageExport::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsCompleted(int $messageCount, ?string $filePath = null): void
    {
        $this->update([
            'status' => 'completed',
            'message_count' => $messageCount,
            'file_path' => $filePath,
        ]);
    }

    public function markAsSent(int $messageCount): void
    {
        $this->update([
            'status' => 'sent',
            'message_count' => $messageCount,
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function markAsNoMessages(): void
    {
        $this->update([
            'status' => 'no_messages',
        ]);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
