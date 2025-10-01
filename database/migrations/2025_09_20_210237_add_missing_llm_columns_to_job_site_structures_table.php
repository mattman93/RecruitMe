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
            // Only add columns that don't already exist
            if (!Schema::hasColumn('job_site_structures', 'submission_flow')) {
                $table->json('submission_flow')->nullable()->after('validation_rules');
            }
            if (!Schema::hasColumn('job_site_structures', 'captcha_config')) {
                $table->json('captcha_config')->nullable()->after('has_captcha');
            }
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
            if (!Schema::hasColumn('job_site_structures', 'successful_applications')) {
                $table->integer('successful_applications')->default(0)->after('last_analyzed_at');
            }
            if (!Schema::hasColumn('job_site_structures', 'failed_applications')) {
                $table->integer('failed_applications')->default(0)->after('successful_applications');
            }
            if (!Schema::hasColumn('job_site_structures', 'llm_model')) {
                $table->string('llm_model')->nullable()->after('success_rate');
            }
            if (!Schema::hasColumn('job_site_structures', 'analysis_version')) {
                $table->string('analysis_version')->default('1.0')->after('llm_model');
            }
            if (!Schema::hasColumn('job_site_structures', 'requires_reanalysis')) {
                $table->boolean('requires_reanalysis')->default(false)->after('analysis_version');
            }
            
            // Add indexes (Laravel will handle duplicates gracefully)
            $table->index(['domain', 'requires_reanalysis'], 'idx_domain_reanalysis');
            $table->index(['last_used_at'], 'idx_last_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->dropColumn([
                'submission_flow', 'captcha_config', 'analysis_time_seconds', 'usage_count',
                'last_used_at', 'last_analyzed_at', 'successful_applications', 
                'failed_applications', 'llm_model', 'analysis_version', 'requires_reanalysis'
            ]);
            
            $table->dropIndex('idx_domain_reanalysis');
            $table->dropIndex('idx_last_used');
        });
    }
};
