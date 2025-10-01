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
        // MySQL doesn't allow direct enum modification, so we need to:
        // 1. Add a new temporary column
        // 2. Copy data to new column
        // 3. Drop old column
        // 4. Rename new column
        
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->enum('automation_strategy_new', [
                'full_auto', 
                'semi_auto', 
                'manual_only', 
                'playwright_llm',
                'playwright_enhanced',
                'hybrid_llm'
            ])->default('semi_auto')->after('automation_strategy');
        });
        
        // Copy existing data
        DB::statement("UPDATE job_site_structures SET automation_strategy_new = automation_strategy");
        
        // Drop old column and rename new one
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->dropColumn('automation_strategy');
        });
        
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->renameColumn('automation_strategy_new', 'automation_strategy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->enum('automation_strategy_new', [
                'full_auto', 
                'semi_auto', 
                'manual_only'
            ])->default('semi_auto')->after('automation_strategy');
        });
        
        // Copy data back, defaulting new values to 'semi_auto'
        DB::statement("UPDATE job_site_structures SET automation_strategy_new = CASE 
            WHEN automation_strategy IN ('full_auto', 'semi_auto', 'manual_only') 
            THEN automation_strategy 
            ELSE 'semi_auto' 
        END");
        
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->dropColumn('automation_strategy');
        });
        
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->renameColumn('automation_strategy_new', 'automation_strategy');
        });
    }
};
