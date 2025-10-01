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
            // Enhanced analysis data
            $table->json('llm_analysis')->nullable()->after('form_fields'); // Store raw LLM response
            $table->json('field_semantic_map')->nullable()->after('llm_analysis'); // Semantic field mappings
            $table->json('validation_rules')->nullable()->after('field_semantic_map'); // Field validation requirements
            $table->json('submission_flow')->nullable()->after('validation_rules'); // Multi-step form flow
            
            // Captcha detection
            $table->boolean('has_captcha')->default(false)->after('submission_flow');
            $table->json('captcha_config')->nullable()->after('has_captcha'); // Captcha details
            
            // Performance tracking
            $table->decimal('analysis_time_seconds', 8, 2)->nullable()->after('captcha_config');
            $table->integer('usage_count')->default(0)->after('analysis_time_seconds');
            $table->timestamp('last_used_at')->nullable()->after('usage_count');
            $table->timestamp('last_analyzed_at')->nullable()->after('last_used_at');
            
            // Success metrics
            $table->integer('successful_applications')->default(0)->after('last_analyzed_at');
            $table->integer('failed_applications')->default(0)->after('successful_applications');
            $table->decimal('success_rate', 5, 2)->nullable()->after('failed_applications');
            
            // Analysis metadata
            $table->string('llm_model')->nullable()->after('success_rate'); // GPT-4, Claude, etc.
            $table->string('analysis_version')->default('1.0')->after('llm_model'); // For schema changes
            $table->boolean('requires_reanalysis')->default(false)->after('analysis_version');
            
            // Indexing for performance
            $table->index(['domain', 'requires_reanalysis']);
            $table->index(['last_used_at']);
            $table->index(['success_rate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_site_structures', function (Blueprint $table) {
            $table->dropColumn([
                'llm_analysis', 'field_semantic_map', 'validation_rules', 'submission_flow',
                'has_captcha', 'captcha_config', 'analysis_time_seconds', 'usage_count',
                'last_used_at', 'last_analyzed_at', 'successful_applications', 
                'failed_applications', 'success_rate', 'llm_model', 'analysis_version',
                'requires_reanalysis'
            ]);
        });
    }
};
