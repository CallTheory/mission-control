<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $to
 * @property string|null $from
 * @property string|null $subject
 * @property string|null $text
 * @property string|null $category
 * @property string|null $attachment_info
 * @property \Carbon\Carbon|null $ignored_at
 * @property \Carbon\Carbon|null $processed_at
 */
class InboundEmail extends Model
{
    use HasFactory;

    protected $guarded = [];
}
