<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            
            // Relationships
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lead_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_site_structure_id')->nullable()->constrained()->onDelete('set null');
            
            // Application details
            $table->string('application_method'); // 'full_auto', 'semi_auto', 'manual', 'iframe'
            $table->enum('status', [
                'queued',           // Ready to apply
                'in_progress',      // Currently applying
                'form_filled',      // Form completed, awaiting user action
                'submitted',        // Successfully submitted
                'failed',           // Application failed
                'blocked',          // Site blocked/CAPTCHA
                'manual_required'   // Needs manual intervention
            ])->default('queued');
            
            // Application data used
            $table->json('form_data_sent'); // Data that was sent to the form
            $table->json('custom_responses')->nullable(); // Job-specific responses
            
            // Automation tracking
            $table->text('automation_log')->nullable(); // Step-by-step log
            $table->json('screenshots')->nullable(); // Screenshot paths for debugging
            $table->text('error_message')->nullable();
            $table->json('playwright_session_data')->nullable();
            
            // Site interaction
            $table->string('final_application_url')->nullable();
            $table->string('confirmation_number')->nullable();
            $table->text('confirmation_message')->nullable();
            
            // Performance metrics
            $table->integer('automation_duration_seconds')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            // Follow-up tracking
            $table->boolean('acknowledgment_received')->default(false);
            $table->timestamp('acknowledgment_received_at')->nullable();
            $table->json('follow_up_emails')->nullable(); // Track company responses
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['lead_id', 'status']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};