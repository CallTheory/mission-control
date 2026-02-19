<?php

namespace App\Models;

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
 * @property string|null $mfax_api_key
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
}
