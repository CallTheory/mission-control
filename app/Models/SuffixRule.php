<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client-specific grant keyed on the linked Intelligent agent's Name.
 *
 * Replaces the hard-coded `-SUP` / `-DISP` str_contains checks that were
 * previously duplicated across the board controllers and board-nav view.
 *
 * @property int $id
 * @property int|null $team_id
 * @property string $match_type contains|suffix|prefix
 * @property string $pattern
 * @property string $capability
 */
class SuffixRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'match_type',
        'pattern',
        'capability',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Whether the given agent name matches this rule.
     */
    public function matches(string $agentName): bool
    {
        if ($this->pattern === '') {
            return false;
        }

        return match ($this->match_type) {
            'suffix' => str_ends_with($agentName, $this->pattern),
            'prefix' => str_starts_with($agentName, $this->pattern),
            default => str_contains($agentName, $this->pattern),
        };
    }
}
