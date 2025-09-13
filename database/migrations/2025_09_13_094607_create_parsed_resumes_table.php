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
        Schema::create('parsed_resumes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Personal Information
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('location')->nullable();
            
            // Professional Summary
            $table->text('professional_summary')->nullable();
            $table->text('objective')->nullable();
            
            // Work Experience (JSON array)
            $table->json('work_experience')->nullable();
            // Each item: {title, company, location, start_date, end_date, is_current, description, achievements}
            
            // Education (JSON array)
            $table->json('education')->nullable();
            // Each item: {degree, field_of_study, school, location, graduation_date, gpa}
            
            // Skills
            $table->json('technical_skills')->nullable(); // Array of technical skills
            $table->json('soft_skills')->nullable(); // Array of soft skills
            $table->json('languages')->nullable(); // Array of languages with proficiency
            
            // Certifications and Awards
            $table->json('certifications')->nullable();
            // Each item: {name, issuer, issue_date, expiry_date, credential_id}
            $table->json('awards')->nullable();
            // Each item: {title, issuer, date, description}
            
            // Projects (JSON array)
            $table->json('projects')->nullable();
            // Each item: {name, description, technologies, url, start_date, end_date}
            
            // File Information
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_type'); // pdf, docx, txt
            $table->integer('file_size'); // in bytes
            
            // Parsing Information
            $table->text('raw_text'); // Full extracted text
            $table->json('parsed_data'); // Complete structured data from AI
            $table->float('parsing_confidence')->nullable(); // AI confidence score
            $table->string('parsing_method'); // 'openai', 'manual', 'hybrid'
            $table->timestamp('parsed_at');
            
            // Additional Metadata
            $table->integer('years_of_experience')->nullable();
            $table->string('current_job_title')->nullable();
            $table->string('current_company')->nullable();
            $table->boolean('is_actively_looking')->default(true);
            $table->json('preferred_locations')->nullable();
            $table->json('preferred_job_types')->nullable(); // remote, hybrid, onsite
            $table->decimal('expected_salary_min', 10, 2)->nullable();
            $table->decimal('expected_salary_max', 10, 2)->nullable();
            
            $table->softDeletes(); // Add soft delete support
            $table->timestamps();
            
            // Indexes for searching
            $table->index('user_id');
            $table->index('email');
            $table->index('current_job_title');
            $table->index('years_of_experience');
            $table->fullText(['raw_text']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parsed_resumes');
    }
};
