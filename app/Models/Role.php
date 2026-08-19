<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Capability;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin-editable, per-team role that maps to a set of capabilities.
 *
 * @property int $id
 * @property int $team_id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property bool $is_system
 * @property int $sort_order
 * @property Collection<int, RoleCapability> $capabilities
 */
class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'key',
        'label',
        'description',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(RoleCapability::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    /**
     * The capability string values granted by this role.
     *
     * @return array<int, string>
     */
    public function capabilityValues(): array
    {
        return $this->capabilities->pluck('capability')->all();
    }

    public function grants(Capability|string $capability): bool
    {
        $value = $capability instanceof Capability ? $capability->value : $capability;

        return in_array($value, $this->capabilityValues(), true);
    }

    /**
     * Replace this role's capabilities with the given set of enum values.
     *
     * @param  array<int, string>  $capabilities
     */
    public function syncCapabilities(array $capabilities): void
    {
        // Only persist values that map to a real, code-defined capability.
        $valid = array_values(array_intersect(
            array_unique($capabilities),
            Capability::values()
        ));

        $this->capabilities()->delete();

        if ($valid !== []) {
            $this->capabilities()->createMany(
                array_map(fn (string $c) => ['capability' => $c], $valid)
            );
        }
    }
}
