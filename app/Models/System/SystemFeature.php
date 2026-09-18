<?php

declare(strict_types=1);

namespace App\Models\System;

use App\Models\Stats\Helpers;
use App\Services\FeatureFlags;
use Illuminate\Database\Eloquent\Model;

/**
 * One system feature flag.
 *
 * Read through {@see FeatureFlags} rather than directly: it holds
 * the Redis cache that keeps these off the hot path, and every read site in the
 * application goes through {@see Helpers::isSystemFeatureEnabled()}.
 *
 * @property int $id
 * @property string $key
 * @property bool $enabled
 */
class SystemFeature extends Model
{
    protected $fillable = ['key', 'enabled'];

    protected $casts = ['enabled' => 'boolean'];
}
