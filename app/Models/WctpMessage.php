<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enterprise_host_id
 * @property string $to
 * @property string $from
 * @property string|null $message
 * @property string $wctp_message_id
 * @property string|null $twilio_sid
 * @property string $direction
 * @property string $status
 * @property string|null $error_message
 * @property string|null $reply_with
 * @property int|null $parent_message_id
 * @property \Carbon\Carbon|null $delivered_at
 * @property \Carbon\Carbon|null $failed_at
 * @property \Carbon\Carbon|null $submitted_at
 * @property \Carbon\Carbon|null $processed_at
 */
class WctpMessage extends Model
{
    use HasFactory;

    protected $table = 'wctp_messages';

    protected $fillable = [
        'enterprise_host_id',
        'to',
        'from',
        'message',
        'wctp_message_id',
        'twilio_sid',
        'direction',
        'status',
        'error_message',
        'delivered_at',
        'failed_at',
        'submitted_at',
        'processed_at',
        'reply_with',
        'parent_message_id',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Encrypt/decrypt the message content automatically.
     */
    protected function message(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }

    /**
     * Get the enterprise host that owns the message.
     */
    public function enterpriseHost(): BelongsTo
    {
        return $this->belongsTo(EnterpriseHost::class);
    }

    /**
     * Scope for pending messages
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for outbound messages
     */
    public function scopeOutbound($query)
    {
        return $query->where('direction', 'outbound');
    }

    /**
     * Scope for inbound messages
     */
    public function scopeInbound($query)
    {
        return $query->where('direction', 'inbound');
    }

    /**
     * Mark message as queued for processing
     */
    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Mark message as sent to Twilio
     */
    public function markAsSent(string $twilioSid): void
    {
        $this->update([
            'status' => 'sent',
            'twilio_sid' => $twilioSid,
        ]);
    }

    /**
     * Mark message as delivered
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark message as failed
     */
    public function markAsFailed(?string $errorMessage = null): void
    {
        $data = [
            'status' => 'failed',
            'failed_at' => now(),
        ];

        if ($errorMessage) {
            $data['error_message'] = $errorMessage;
        }

        $this->update($data);
    }
}
