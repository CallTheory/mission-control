<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        'delivered_at',
        'failed_at',
        'reply_with',
        'parent_message_id',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

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
    public function markAsFailed(string $errorMessage = null): void
    {
        $data = [
            'status' => 'failed',
            'failed_at' => now(),
        ];
        
        if ($errorMessage) {
            $data['message'] = $this->message . ' [Error: ' . $errorMessage . ']';
        }
        
        $this->update($data);
    }

}