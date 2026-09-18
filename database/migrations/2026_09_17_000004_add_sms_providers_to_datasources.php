<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credentials for the two SMS carriers joining Twilio on the WCTP gateway, plus the
 * system-wide default carrier.
 *
 * Secrets are `text` because the EncryptedSerialized cast stores ciphertext, which
 * is far longer than the value; see App\Models\DataSource. Callback credentials are
 * separate from API credentials because they travel the other way -- the carrier
 * presents them to us on an inbound webhook.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('sms_default_provider')->nullable();

            $table->string('bandwidth_account_id')->nullable();
            $table->text('bandwidth_api_token')->nullable();
            $table->text('bandwidth_api_secret')->nullable();
            $table->string('bandwidth_application_id')->nullable();
            $table->string('bandwidth_from_number')->nullable();
            $table->string('bandwidth_callback_username')->nullable();
            $table->text('bandwidth_callback_password')->nullable();
            $table->text('bandwidth_callback_token')->nullable();

            $table->string('commio_account_id')->nullable();
            $table->string('commio_username')->nullable();
            $table->text('commio_api_token')->nullable();
            $table->string('commio_from_number')->nullable();
            $table->string('commio_callback_username')->nullable();
            $table->text('commio_callback_password')->nullable();
            $table->text('commio_callback_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn([
                'sms_default_provider',
                'bandwidth_account_id',
                'bandwidth_api_token',
                'bandwidth_api_secret',
                'bandwidth_application_id',
                'bandwidth_from_number',
                'bandwidth_callback_username',
                'bandwidth_callback_password',
                'bandwidth_callback_token',
                'commio_account_id',
                'commio_username',
                'commio_api_token',
                'commio_from_number',
                'commio_callback_username',
                'commio_callback_password',
                'commio_callback_token',
            ]);
        });
    }
};
