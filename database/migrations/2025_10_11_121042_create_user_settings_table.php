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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Email Notifications
            $table->boolean('notify_new_matches')->default(true);
            $table->boolean('notify_application_updates')->default(true);
            $table->enum('email_digest_frequency', ['immediate', 'daily', 'weekly'])->default('daily');

            // Job Preferences
            $table->integer('min_salary')->default(100); // in thousands
            $table->integer('max_salary')->default(150); // in thousands
            $table->string('preferred_location')->nullable();
            $table->string('preferred_job_title')->nullable();
            $table->json('employment_types')->nullable();
            $table->json('work_arrangement')->nullable();
            $table->boolean('willing_to_relocate')->default(false);

            // Application Settings
            $table->boolean('queue_auto_apply')->default(false);
            $table->boolean('autonomous_auto_apply')->default(false);
            $table->integer('max_applications_per_day')->default(10);

            // Privacy Settings
            $table->boolean('show_to_recruiters')->default(true);
            $table->boolean('hide_from_current_employer')->default(false);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
