<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeToDataSources extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->string('stripe_test_secret_key')->nullable();
            $table->string('stripe_prod_secret_key')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('data_sources', function (Blueprint $table) {
            $table->dropColumn('stripe_test_secret_key');
            $table->dropColumn('stripe_prod_secret_key');
        });
    }
}
