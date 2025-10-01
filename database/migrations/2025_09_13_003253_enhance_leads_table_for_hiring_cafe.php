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
        Schema::table('leads', function (Blueprint $table) {
            // Job identification and source
            $table->string('external_id')->nullable()->after('id');
            $table->string('source_platform')->default('hiring.cafe')->after('external_id');
            $table->string('board_token')->nullable()->after('source_platform');
            $table->string('apply_url')->nullable()->after('source_url');
            
            // Enhanced job information
            $table->string('core_job_title')->nullable()->after('job_title');
            $table->string('job_title_raw')->nullable()->after('core_job_title');
            $table->text('requirements_summary')->nullable()->after('description');
            $table->json('technical_tools')->nullable()->after('requirements_summary');
            $table->string('job_category')->nullable()->after('technical_tools');
            $table->string('seniority_level')->nullable()->after('experience_level');
            $table->string('role_type')->nullable()->after('seniority_level'); // "Individual Contributor", "People Manager"
            
            // Enhanced compensation
            $table->decimal('yearly_min_compensation', 12, 2)->nullable()->after('pay_range');
            $table->decimal('yearly_max_compensation', 12, 2)->nullable()->after('yearly_min_compensation');
            $table->decimal('hourly_min_compensation', 8, 2)->nullable()->after('yearly_max_compensation');
            $table->decimal('hourly_max_compensation', 8, 2)->nullable()->after('hourly_min_compensation');
            $table->string('listed_compensation_currency', 3)->default('USD')->after('hourly_max_compensation');
            $table->string('listed_compensation_frequency')->nullable()->after('listed_compensation_currency'); // "Yearly", "Hourly"
            $table->boolean('is_compensation_transparent')->default(false)->after('listed_compensation_frequency');
            
            // Work arrangements and location
            $table->string('workplace_type')->nullable()->after('location'); // "Remote", "Hybrid", "Onsite"
            $table->json('workplace_countries')->nullable()->after('workplace_type');
            $table->json('workplace_states')->nullable()->after('workplace_countries');
            $table->json('workplace_cities')->nullable()->after('workplace_states');
            $table->string('formatted_workplace_location')->nullable()->after('workplace_cities');
            
            // Requirements and qualifications
            $table->integer('min_industry_and_role_yoe')->nullable()->after('formatted_workplace_location');
            $table->string('bachelors_degree_requirement')->nullable()->after('min_industry_and_role_yoe'); // "Required", "Preferred", "Not Mentioned"
            $table->string('masters_degree_requirement')->nullable()->after('bachelors_degree_requirement');
            $table->string('doctorate_degree_requirement')->nullable()->after('masters_degree_requirement');
            $table->json('licenses_or_certifications')->nullable()->after('doctorate_degree_requirement');
            
            // Company information
            $table->string('company_website')->nullable()->after('company');
            $table->string('company_linkedin_url')->nullable()->after('company_website');
            $table->integer('company_size')->nullable()->after('company_linkedin_url'); // num_employees
            $table->json('company_industries')->nullable()->after('company_size');
            $table->string('company_tagline')->nullable()->after('company_industries');
            $table->integer('company_founded_year')->nullable()->after('company_tagline');
            $table->string('company_funding_series')->nullable()->after('company_founded_year'); // "Series C"
            $table->json('company_investors')->nullable()->after('company_funding_series');
            $table->string('company_headquarters_country')->nullable()->after('company_investors');
            
            // Benefits and perks
            $table->boolean('retirement_plan')->default(false)->after('company_headquarters_country');
            $table->boolean('generous_parental_leave')->default(false)->after('retirement_plan');
            $table->boolean('visa_sponsorship')->default(false)->after('generous_parental_leave');
            $table->boolean('relocation_assistance')->default(false)->after('visa_sponsorship');
            $table->boolean('remote_work_available')->default(false)->after('relocation_assistance');
            $table->boolean('tuition_reimbursement')->default(false)->after('remote_work_available');
            $table->boolean('generous_paid_time_off')->default(false)->after('tuition_reimbursement');
            
            // Work environment details
            $table->string('physical_environment')->nullable()->after('generous_paid_time_off'); // "Office", "Outdoor", etc.
            $table->string('oral_communication_level')->nullable()->after('physical_environment'); // "Low", "Medium", "High"
            $table->string('physical_labor_intensity')->nullable()->after('oral_communication_level'); // "Low", "Medium", "High"
            $table->string('computer_usage')->nullable()->after('physical_labor_intensity'); // "Low", "Medium", "High"
            $table->string('cognitive_demand')->nullable()->after('computer_usage'); // "Low", "Medium", "High"
            $table->string('security_clearance')->nullable()->after('cognitive_demand'); // "None", "Secret", etc.
            
            // Data quality and tracking
            $table->timestamp('estimated_publish_date')->nullable()->after('is_active');
            $table->boolean('is_expired')->default(false)->after('estimated_publish_date');
            $table->string('data_quality_score')->nullable()->after('is_expired'); // "high", "medium", "low"
            $table->timestamp('last_scraped_at')->nullable()->after('data_quality_score');
            $table->string('requisition_id')->nullable()->after('last_scraped_at');
            $table->string('collapse_key')->nullable()->after('requisition_id'); // For deduplication
            
            // Add indexes for better performance
            $table->index(['external_id', 'source_platform']);
            $table->index(['company', 'job_category']);
            $table->index(['workplace_type', 'seniority_level']);
            $table->index(['yearly_min_compensation', 'yearly_max_compensation']);
            $table->index(['estimated_publish_date', 'is_active']);
            $table->index(['is_expired', 'last_scraped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['external_id', 'source_platform']);
            $table->dropIndex(['company', 'job_category']);
            $table->dropIndex(['workplace_type', 'seniority_level']);
            $table->dropIndex(['yearly_min_compensation', 'yearly_max_compensation']);
            $table->dropIndex(['estimated_publish_date', 'is_active']);
            $table->dropIndex(['is_expired', 'last_scraped_at']);
            
            // Drop all new columns
            $table->dropColumn([
                'external_id', 'source_platform', 'board_token', 'apply_url',
                'core_job_title', 'job_title_raw', 'requirements_summary', 'technical_tools',
                'job_category', 'seniority_level', 'role_type',
                'yearly_min_compensation', 'yearly_max_compensation', 'hourly_min_compensation', 
                'hourly_max_compensation', 'listed_compensation_currency', 'listed_compensation_frequency',
                'is_compensation_transparent', 'workplace_type', 'workplace_countries', 'workplace_states',
                'workplace_cities', 'formatted_workplace_location', 'min_industry_and_role_yoe',
                'bachelors_degree_requirement', 'masters_degree_requirement', 'doctorate_degree_requirement',
                'licenses_or_certifications', 'company_website', 'company_linkedin_url', 'company_size',
                'company_industries', 'company_tagline', 'company_founded_year', 'company_funding_series',
                'company_investors', 'company_headquarters_country', 'retirement_plan', 'generous_parental_leave',
                'visa_sponsorship', 'relocation_assistance', 'remote_work_available', 'tuition_reimbursement',
                'generous_paid_time_off', 'physical_environment', 'oral_communication_level',
                'physical_labor_intensity', 'computer_usage', 'cognitive_demand', 'security_clearance',
                'estimated_publish_date', 'is_expired', 'data_quality_score', 'last_scraped_at',
                'requisition_id', 'collapse_key'
            ]);
        });
    }
};
