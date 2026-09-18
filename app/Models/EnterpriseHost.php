<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SmsProvider;
use App\Services\Sms\SmsGatewayManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $senderID
 * @property string|null $securityCode
 * @property bool $enabled
 * @property string|null $callback_url
 * @property array|null $phone_numbers
 * @property array|null $number_providers
 * @property int|null $team_id
 * @property int $message_count
 * @property Carbon|null $last_message_at
 */
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
        'number_providers',  // Map of digits-only number => SMS provider key
        'team_id',
        'message_count',
        'last_message_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'phone_numbers' => 'array',
        'number_providers' => 'array',
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
            $expected = $this->securityCode;

            if (! is_string($expected) || $expected === '' || $code === '') {
                return false;
            }

            // Constant-time comparison to avoid leaking the code via timing.
            return hash_equals($expected, $code);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a phone number is assigned to this host
     */
    public function hasPhoneNumber(string $phoneNumber): bool
    {
        if (empty($this->phone_numbers)) {
            return false;
        }

        $normalized = static::normalizeNumber($phoneNumber);

        foreach ($this->phone_numbers as $number) {
            if (static::normalizeNumber((string) $number) === $normalized) {
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
        if (! empty($this->phone_numbers)) {
            return $this->phone_numbers[0];
        }

        // No number of its own: fall back to the system default carrier's number.
        return app(SmsGatewayManager::class)->default()->fromNumber();
    }

    /**
     * The carrier that owns one of this host's numbers, or null when the number has
     * not been assigned one and should use the system default.
     *
     * A DID belongs to exactly one carrier, so this -- not a per-host setting -- is
     * what decides which gateway an outbound message goes out through, and a host
     * may legitimately hold numbers from several carriers at once.
     */
    public function providerForNumber(?string $phoneNumber): ?SmsProvider
    {
        if (blank($phoneNumber) || empty($this->number_providers)) {
            return null;
        }

        return SmsProvider::tryFromKey($this->number_providers[static::normalizeNumber($phoneNumber)] ?? null);
    }

    /**
     * The carrier for the number this host sends from.
     */
    public function outboundProvider(): ?SmsProvider
    {
        return $this->providerForNumber($this->getOutboundPhoneNumber());
    }

    /**
     * Digits only, with the North American country code filled in, so numbers
     * written `+1 (555) 123-4567` and `5551234567` compare equal and key the same
     * entry in `number_providers`.
     */
    public static function normalizeNumber(string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?? '';

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return $digits;
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
