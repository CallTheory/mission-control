<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional RingCentral extensions to send faxes through.
 *
 * RingCentral counts its fax rate limit per authenticated user (extension) per app, so
 * each extension added here brings its own allowance. Each needs its own JWT: sending as
 * another extension under the primary extension's token would still count against the
 * primary's limit. The client id, secret and endpoint stay on the data source — every
 * sender authenticates against the same app.
 *
 * The primary extension (the JWT on the data source) is not a row here. With no rows,
 * sending behaves exactly as it did with a single extension.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ringcentral_senders', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            // Encrypted by the model's cast; a JWT is a long-lived credential.
            $table->text('jwt_token');
            // Filled from the token's owner_id when the sender is saved, so the log and
            // the fax records can say which extension sent.
            $table->string('extension_id', 32)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ringcentral_senders');
    }
};
