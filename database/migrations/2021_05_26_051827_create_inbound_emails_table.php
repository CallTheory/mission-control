<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInboundEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('inbound_emails', function (Blueprint $table) {
            $table->id();
            $table->text('headers')->nullable();
            $table->text('dkim')->nullable();
            $table->text('content_ids')->nullable();
            $table->text('to')->nullable();
            $table->text('from')->nullable();
            $table->text('sender_ip')->nullable();
            $table->text('envelope')->nullable();
            $table->text('attachments')->nullable();
            $table->text('subject')->nullable();
            $table->text('spam_report')->nullable();
            $table->text('spam_score')->nullable();
            $table->text('attachment_info')->nullable();
            $table->text('charsets')->nullable();
            $table->text('spf')->nullable();
            $table->text('text')->nullable();
            $table->text('html')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('ignored_at')->nullable();
            $table->foreignId('inbound_email_rules_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_emails');
    }
}
