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
        Schema::create('scheduler_runs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name')->index(); // e.g., 'FetchJobsFromHiringCafe'
            $table->enum('status', ['success', 'failed', 'rate_limited'])->index();
            $table->string('search_term_used')->nullable();
            $table->integer('jobs_collected')->default(0);
            $table->integer('duplicates_skipped')->default(0);
            $table->integer('errors_encountered')->default(0);
            $table->integer('total_jobs_in_db')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for filtering
            $table->index('created_at');
            $table->index(['job_name', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduler_runs');
    }
};
