<?php

namespace App\Models;

use App\Casts\EncryptedSerialized;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $is_db_host
 * @property string|null $is_db_port
 * @property string|null $is_db_data
 * @property string|null $is_db_user
 * @property string|null $is_db_pass
 * @property string|null $is_web_api_endpoint
 * @property string|null $is_agent_username
 * @property string|null $is_agent_password
 * @property string|null $miteamweb_site
 * @property string|null $marketing_site
 * @property string|null $ringcentral_client_id
 * @property string|null $ringcentral_client_secret
 * @property string|null $ringcentral_jwt_token
 * @property string|null $ringcentral_api_endpoint
 * @property bool $ringcentral_enabled
 * @property string|null $mfax_api_key
 * @property bool $mfax_enabled
 * @property string|null $people_praise_basic_auth_user
 * @property string|null $people_praise_basic_auth_pass
 * @property string|null $twilio_from_number
 * @property string|null $type
 * @property bool|null $enabled
 */
class DataSource extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Sensitive columns are encrypted transparently via the EncryptedSerialized cast
     * (see casts() below). Hiding them keeps the ciphertext out of
     * toArray()/toJson()/model logging so a stray dump or log of a DataSource can
     * never leak a credential blob.
     */
    protected $hidden = [
        'client_db_pass',
        'is_db_pass',
        'is_agent_password',
        'mfax_basic_auth_username',
        'mfax_basic_auth_password',
        'mfax_api_key',
        'people_praise_basic_auth_user',
        'people_praise_basic_auth_pass',
        'ringcentral_client_secret',
        'ringcentral_jwt_token',
        'stripe_test_secret_key',
        'stripe_prod_secret_key',
        'twilio_account_sid',
        'twilio_auth_token',
    ];

    protected function casts(): array
    {
        return [
            'ringcentral_enabled' => 'boolean',
            'mfax_enabled' => 'boolean',
            'enabled' => 'boolean',

            // Credentials: encrypted at rest, transparent to callers. Callers must
            // read/write PLAINTEXT — do NOT wrap these in encrypt()/decrypt().
            'client_db_pass' => EncryptedSerialized::class,
            'is_db_pass' => EncryptedSerialized::class,
            'is_agent_password' => EncryptedSerialized::class,
            'mfax_basic_auth_username' => EncryptedSerialized::class,
            'mfax_basic_auth_password' => EncryptedSerialized::class,
            'mfax_api_key' => EncryptedSerialized::class,
            'people_praise_basic_auth_user' => EncryptedSerialized::class,
            'people_praise_basic_auth_pass' => EncryptedSerialized::class,
            'ringcentral_client_secret' => EncryptedSerialized::class,
            'ringcentral_jwt_token' => EncryptedSerialized::class,
            'stripe_test_secret_key' => EncryptedSerialized::class,
            'stripe_prod_secret_key' => EncryptedSerialized::class,
            'twilio_account_sid' => EncryptedSerialized::class,
            'twilio_auth_token' => EncryptedSerialized::class,
        ];
    }
}
