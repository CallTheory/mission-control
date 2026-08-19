<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $role_id
 * @property string $capability
 */
class RoleCapability extends Model
{
    protected $table = 'role_capability';

    protected $fillable = [
        'role_id',
        'capability',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
