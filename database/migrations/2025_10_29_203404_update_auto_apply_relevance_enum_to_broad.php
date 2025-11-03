<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update any existing 'low' values to 'broad'
        DB::table('user_settings')
            ->where('auto_apply_relevance', 'low')
            ->update(['auto_apply_relevance' => 'broad']);

        // Alter the enum column to use 'broad' instead of 'low'
        DB::statement("ALTER TABLE user_settings MODIFY COLUMN auto_apply_relevance ENUM('high', 'medium', 'broad') DEFAULT 'high'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Update any existing 'broad' values back to 'low'
        DB::table('user_settings')
            ->where('auto_apply_relevance', 'broad')
            ->update(['auto_apply_relevance' => 'low']);

        // Alter the enum column back to use 'low' instead of 'broad'
        DB::statement("ALTER TABLE user_settings MODIFY COLUMN auto_apply_relevance ENUM('high', 'medium', 'low') DEFAULT 'high'");
    }
};
