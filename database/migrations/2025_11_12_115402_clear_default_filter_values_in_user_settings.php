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
        // First, change the column defaults to nullable
        Schema::table('user_settings', function (Blueprint $table) {
            $table->integer('min_salary')->nullable()->default(null)->change();
            $table->integer('max_salary')->nullable()->default(null)->change();
        });

        // Then update existing records that have the old default values to NULL
        DB::table('user_settings')
            ->where('min_salary', 100)
            ->where('max_salary', 150)
            ->update([
                'min_salary' => null,
                'max_salary' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the old defaults
        Schema::table('user_settings', function (Blueprint $table) {
            $table->integer('min_salary')->default(100)->change();
            $table->integer('max_salary')->default(150)->change();
        });
    }
};
