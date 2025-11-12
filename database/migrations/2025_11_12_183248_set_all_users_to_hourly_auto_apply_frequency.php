<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all existing user_settings to use hourly frequency
        DB::statement("UPDATE user_settings SET auto_apply_frequency = 'hourly'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed - this is a one-time data fix
    }
};
