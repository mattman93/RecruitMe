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
        // Update all existing records to clear default filter values
        DB::table('user_settings')
            ->where(function($query) {
                $query->where('min_salary', 100)
                      ->where('max_salary', 150);
            })
            ->orWhere(function($query) {
                $query->whereNotNull('employment_types')
                      ->where('employment_types', 'LIKE', '%"fullTime":true%');
            })
            ->orWhere(function($query) {
                $query->whereNotNull('work_arrangement')
                      ->where('work_arrangement', 'LIKE', '%"remote":true%');
            })
            ->update([
                'min_salary' => null,
                'max_salary' => null,
                'employment_types' => null,
                'work_arrangement' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - we're just clearing defaults
    }
};
