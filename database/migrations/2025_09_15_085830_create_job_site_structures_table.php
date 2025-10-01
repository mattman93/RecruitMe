<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_site_structures', function (Blueprint $table) {
            $table->id();
            
            // Site identification
            $table->string('domain'); // e.g., 'jobs.ashbyhq.com'
            $table->string('platform_name'); // e.g., 'Ashby', 'Workday', 'Greenhouse'
            $table->string('site_pattern')->nullable(); // URL pattern for recognition
            
            // Application flow structure
            $table->json('application_flow'); // Step-by-step navigation
            $table->json('form_fields'); // All possible form fields and their selectors
            $table->json('button_selectors'); // Apply buttons, submit buttons, etc.
            
            // Form automation data
            $table->json('field_mappings'); // Map our data to their field names
            $table->json('validation_rules')->nullable(); // Site-specific validation
            $table->json('dynamic_fields')->nullable(); // Fields that appear conditionally
            
            // Anti-bot measures
            $table->boolean('has_captcha')->default(false);
            $table->string('captcha_type')->nullable(); // 'recaptcha', 'hcaptcha', etc.
            $table->json('anti_bot_measures')->nullable(); // Other measures detected
            
            // Success/failure indicators
            $table->json('success_indicators'); // How to detect successful submission
            $table->json('error_selectors')->nullable(); // How to detect errors
            
            // Site metadata
            $table->integer('success_rate')->default(0); // % of successful applications
            $table->integer('total_attempts')->default(0);
            $table->text('notes')->nullable(); // Human observations
            $table->enum('automation_strategy', ['full_auto', 'semi_auto', 'manual_only'])->default('semi_auto');
            
            // Learning and updates
            $table->timestamp('last_structure_update')->nullable();
            $table->timestamp('last_successful_application')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index('domain');
            $table->index('platform_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_site_structures');
    }
};