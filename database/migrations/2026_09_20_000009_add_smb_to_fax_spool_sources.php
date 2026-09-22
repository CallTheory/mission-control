<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credentials for reaching an Intelligent Series server's share directly, instead of
 * through a kernel CIFS mount.
 *
 * Until now these lived in /etc/fstab or a root-owned credentials file, which meant a
 * second fax server was an ops ticket rather than a form, and an expired password looked
 * like "faxing is broken" with nothing in the application able to say otherwise.
 *
 * `share_map` exists because the share *is* the provider in the existing topology
 * (//isserver/mfax, \\mission-control\mfax) — there is no share above it to act as a
 * root — so a source needs to know which share to use, with an optional path inside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fax_spool_sources', function (Blueprint $table) {
            $table->string('smb_host')->nullable();
            $table->json('share_map')->nullable();
            $table->text('smb_username')->nullable();
            $table->text('smb_password')->nullable();
            $table->string('smb_domain')->nullable();
            $table->string('min_protocol', 16)->nullable()->default('SMB2');
            $table->string('max_protocol', 16)->nullable();
            $table->unsignedSmallInteger('timeout_seconds')->default(15);

            // The mount this source was detected from, kept so the migration can be
            // reversed and so nobody unmounts a share that is still in use.
            $table->string('legacy_mount_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fax_spool_sources', function (Blueprint $table) {
            $table->dropColumn([
                'smb_host', 'share_map', 'smb_username', 'smb_password',
                'smb_domain', 'min_protocol', 'max_protocol', 'timeout_seconds',
                'legacy_mount_path',
            ]);
        });
    }
};
