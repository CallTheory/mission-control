<?php

namespace App\Models\Stats\BoardCheck;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * The user who performed the activity. Declared so the listing can eager-load it;
     * it previously resolved the name with a User::find() inside the render loop.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
