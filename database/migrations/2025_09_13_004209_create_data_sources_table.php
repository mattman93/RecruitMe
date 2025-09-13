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
        Schema::create('data_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "hiring.cafe", "indeed", "linkedin"
            $table->string('url'); // API endpoint or base URL
            $table->enum('fetch_type', ['API', 'SCRAPE', 'RSS', 'CSV'])->default('API');
            $table->enum('data_return_type', ['json', 'xml', 'html', 'csv'])->default('json');
            $table->json('headers')->nullable(); // HTTP headers required for requests
            $table->json('custom_data')->nullable(); // Additional configuration (request body, auth, etc.)
            $table->boolean('is_active')->default(true);
            $table->integer('rate_limit_per_hour')->default(100); // Rate limiting
            $table->timestamp('last_fetched_at')->nullable();
            $table->integer('total_jobs_fetched')->default(0);
            $table->text('description')->nullable(); // Human-readable description
            $table->timestamps();
            
            // Add indexes
            $table->index(['is_active', 'fetch_type']);
            $table->index('last_fetched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
