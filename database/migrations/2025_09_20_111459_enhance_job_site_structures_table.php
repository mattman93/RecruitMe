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
        Schema::table('job_site_structures', function (Blueprint $table) {
            // Enhanced analysis data - only add if they don't exist
            if (!Schema::hasColumn('job_site_structures', 'llm_analysis')) {
                $table->json('llm_analysis')->nullable()->after('form_fields');
            }
            if (!Schema::hasColumn('job_site_structures', 'field_semantic_map')) {
                $table->json('field_semantic_map')->nullable()->after('llm_analysis');
            }
            // Note: validation_rules already exists from the original table creation
            if (!Schema::hasColumn('job_site_structures', 'submission_flow')) {
                $table->json('submission_flow')->nullable()->after('validation_rules');
            }
            
            // Captcha detection - has_captcha already exists, but captcha_config might not
            if (!Schema::hasColumn('job_site_structures', 'captcha_config')) {
                $table->json('captcha_config')->nullable()->after('has_captcha');
            }
            
            // Performance tracking
            if (!Schema::hasColumn('job_site_structures', 'analysis_time_seconds')) {
                $table->decimal('analysis_time_seconds', 8, 2)->nullable()->after('captcha_config');
            }
            if (!Schema::hasColumn('job_site_structures', 'usage_count')) {
                $table->integer('usage_count')->default(0)->after('analysis_time_seconds');
            }
            if (!Schema::hasColumn('job_site_structures', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('usage_count');
            }
            if (!Schema::hasColumn('job_site_structures', 'last_analyzed_at')) {
                $table->timestamp('last_analyzed_at')->nullable()->after('last_used_at');
            }
            
            // Success metrics
            if (!Schema::hasColumn('job_site_structures', 'successful_applications')) {
                $table->integer('successful_applications')->default(0)->after('last_analyzed_at');
            }
            if (!Schema::hasColumn('job_site_structures', 'failed_applications')) {
                $table->integer('failed_applications')->default(0)->after('successful_applications');
            }
            // Note: success_rate already exists, skip it
            
            // Analysis metadata
            if (!Schema::hasColumn('job_site_structures', 'llm_model')) {
                $table->string('llm_model')->nullable()->after('success_rate');
            }
            if (!Schema::hasColumn('job_site_structures', 'analysis_version')) {
                $table->string('analysis_version')->default('1.0')->after('llm_model');
            }
            if (!Schema::hasColumn('job_site_structures', 'requires_reanalysis')) {
                $table->boolean('requires_reanalysis')->default(false)->after('analysis_version');
            }
            
            // Add indexes - Laravel will handle duplicates gracefully
            try {
                $table->index(['domain', 'requires_reanalysis']);
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            try {
                $table->index(['last_used_at']);
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
            try {
                $table->index(['success_rate']);
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_site_structures', function (Blueprint $table) {
            // Only drop columns that were actually added by this migration
            $columnsToDrop = [];
            
            $columnsToCheck = [
                'llm_analysis', 'field_semantic_map', 'submission_flow',
                'captcha_config', 'analysis_time_seconds', 'usage_count',
                'last_used_at', 'last_analyzed_at', 'successful_applications', 
                'failed_applications', 'llm_model', 'analysis_version',
                'requires_reanalysis'
            ];
            
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('job_site_structures', $column)) {
                    $columnsToDrop[] = $column;
                }
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
