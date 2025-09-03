<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnterpriseHost extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'senderID',
        'securityCode',
        'enabled',
        'callback_url',
        'phone_numbers',  // Array of phone numbers mapped to this host
        'team_id',
        'message_count',
        'last_message_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'phone_numbers' => 'array',
        'message_count' => 'integer',
        'last_message_at' => 'datetime',
    ];

    /**
     * Encrypt/decrypt the security code automatically
     */
    protected function securityCode(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? decrypt($value) : null,
            set: fn ($value) => $value ? encrypt($value) : null,
        );
    }


    /**
     * Get the team that owns the enterprise host.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the messages for the enterprise host.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(WctpMessage::class);
    }

    /**
     * Scope a query to only include enabled hosts.
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Scope a query to find by senderID.
     */
    public function scopeBySenderID($query, string $senderID)
    {
        return $query->where('senderID', $senderID);
    }

    /**
     * Increment the message count and update last message timestamp
     */
    public function recordMessage(): void
    {
        $this->increment('message_count');
        $this->update(['last_message_at' => now()]);
    }

    /**
     * Validate security code
     */
    public function validateSecurityCode(string $code): bool
    {
        try {
            return $this->securityCode === $code;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a phone number is assigned to this host
     */
    public function hasPhoneNumber(string $phoneNumber): bool
    {
        if (! $this->phone_numbers || empty($this->phone_numbers)) {
            return false;
        }

        // Normalize the phone number for comparison (keep only digits)
        $normalized = preg_replace('/\D+/', '', $phoneNumber);
        
        // Also try with +1 prefix
        $withCountryCode = '1' . $normalized;
        $withoutCountryCode = ltrim($normalized, '1');

        foreach ($this->phone_numbers as $number) {
            $cleanNumber = preg_replace('/\D+/', '', $number);
            if ($cleanNumber === $normalized || 
                $cleanNumber === $withCountryCode || 
                $cleanNumber === $withoutCountryCode) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the phone number to use for outbound messages
     */
    public function getOutboundPhoneNumber(): ?string
    {
        // Use the first assigned number
        if ($this->phone_numbers && count($this->phone_numbers) > 0) {
            return $this->phone_numbers[0];
        }

        // Fall back to the global Twilio number from DataSource if no numbers assigned
        $dataSource = \App\Models\DataSource::where('type', 'twilio')
            ->where('enabled', true)
            ->first();

        return $dataSource ? $dataSource->twilio_from_number : null;
    }

    /**
     * Find an Enterprise Host by phone number for inbound routing
     */
    public static function findByPhoneNumber(string $phoneNumber): ?self
    {
        // Get all enabled hosts and check their phone numbers
        $hosts = static::where('enabled', true)->get();

        foreach ($hosts as $host) {
            if ($host->hasPhoneNumber($phoneNumber)) {
                return $host;
            }
        }

        return null;
    }
}
