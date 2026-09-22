<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FaxProvider;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Forces particular faxes through a particular provider.
 *
 * @property int $id
 * @property string $match_type
 * @property string $match_value
 * @property FaxProvider $provider
 * @property bool $allow_failover
 * @property bool $enabled
 * @property string|null $note
 */
class FaxProviderPin extends Model
{
    use HasFactory;

    public const MATCH_NUMBER = 'number';

    public const MATCH_ACCOUNT = 'account';

    protected $fillable = [
        'match_type',
        'match_value',
        'provider',
        'allow_failover',
        'enabled',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'provider' => FaxProvider::class,
            'allow_failover' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    /**
     * Normalise on the way in so a pin entered as `(555) 123-4567` matches a `.fs` that
     * carries `5551234567;`. Account numbers are compared as trimmed strings.
     */
    public function setMatchValueAttribute(?string $value): void
    {
        $this->attributes['match_value'] = $this->match_type === self::MATCH_NUMBER
            ? PhoneNumber::normalize((string) $value)
            : trim((string) $value);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public static function forNumber(?string $number): ?self
    {
        $normalized = PhoneNumber::normalize((string) $number);

        return $normalized === ''
            ? null
            : static::query()->enabled()->where('match_type', self::MATCH_NUMBER)->where('match_value', $normalized)->first();
    }

    public static function forAccount(?string $accountNumber): ?self
    {
        $account = trim((string) $accountNumber);

        return $account === ''
            ? null
            : static::query()->enabled()->where('match_type', self::MATCH_ACCOUNT)->where('match_value', $account)->first();
    }
}
