<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoicemailDigestLog extends Model
{
    use HasFactory;

    protected $table = 'voicemail_digest_logs';

    protected $fillable = [
        'voicemail_digest_id',
        'team_id',
        'start_date',
        'end_date',
        'recipients',
        'subject',
        'recording_count',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'sent_at' => 'datetime',
        'recording_count' => 'integer',
    ];

    public function voicemailDigest(): BelongsTo
    {
        return $this->belongsTo(VoicemailDigest::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function markAsSent(int $recordingCount): void
    {
        $this->update([
            'status' => 'sent',
            'recording_count' => $recordingCount,
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

    public function markAsNoRecordings(): void
    {
        $this->update([
            'status' => 'no_recordings',
        ]);
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->where('team_id', $teamId);
    }
}
