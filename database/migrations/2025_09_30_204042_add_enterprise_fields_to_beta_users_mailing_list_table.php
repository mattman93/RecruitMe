<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('beta_users_mailing_list', function (Blueprint $table) {
            $table->string('organization')->nullable()->after('name');
            $table->json('additional_data')->nullable()->after('user_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beta_users_mailing_list', function (Blueprint $table) {
            $table->dropColumn(['organization', 'additional_data']);
        });
    }
};
