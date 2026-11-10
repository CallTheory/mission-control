<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Faxing\RingCentralExtension;
use Illuminate\Database\Eloquent\Model;

/**
 * An additional RingCentral extension to send faxes through.
 *
 * RingCentral's fax rate limit is per authenticated extension per app, so each enabled
 * sender adds its own allowance. The primary extension — the JWT on the data source — is
 * not stored here and is always part of the pool.
 *
 * @property int $id
 * @property string $label
 * @property string $jwt_token
 * @property string|null $extension_id
 * @property bool $enabled
 * @property int $sort
 */
class RingCentralSender extends Model
{
    protected $table = 'ringcentral_senders';

    protected $fillable = [
        'label',
        'jwt_token',
        'extension_id',
        'enabled',
        'sort',
    ];

    protected $hidden = ['jwt_token'];

    protected function casts(): array
    {
        return [
            'jwt_token' => 'encrypted',
            'enabled' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * The key the rest of the fax pipeline identifies this sender by. Stable for the life
     * of the row and never reused, unlike the label.
     */
    public function senderKey(): string
    {
        return RingCentralExtension::rowKey($this->id);
    }

    public function toExtension(): RingCentralExtension
    {
        return new RingCentralExtension($this->senderKey(), $this->label, $this->jwt_token, $this->extension_id);
    }
}
