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
        Schema::table('users', function (Blueprint $table) {
            $table->integer('resume_replacement_count')->default(0)->after('remember_token');
            $table->timestamp('last_resume_replacement_reset_at')->nullable()->after('resume_replacement_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['resume_replacement_count', 'last_resume_replacement_reset_at']);
        });
    }
};
