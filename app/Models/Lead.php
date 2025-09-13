<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        // Original fields
        'job_title',
        'company',
        'pay_range',
        'description',
        'location',
        'employment_type',
        'experience_level',
        'source_url',
        'is_active',

        // Job identification and source
        'external_id',
        'source_platform',
        'board_token',
        'apply_url',

        // Enhanced job information
        'core_job_title',
        'job_title_raw',
        'requirements_summary',
        'technical_tools',
        'job_category',
        'seniority_level',
        'role_type',

        // Enhanced compensation
        'yearly_min_compensation',
        'yearly_max_compensation',
        'hourly_min_compensation',
        'hourly_max_compensation',
        'listed_compensation_currency',
        'listed_compensation_frequency',
        'is_compensation_transparent',

        // Work arrangements and location
        'workplace_type',
        'workplace_countries',
        'workplace_states',
        'workplace_cities',
        'formatted_workplace_location',

        // Requirements and qualifications
        'min_industry_and_role_yoe',
        'bachelors_degree_requirement',
        'masters_degree_requirement',
        'doctorate_degree_requirement',
        'licenses_or_certifications',

        // Company information
        'company_website',
        'company_linkedin_url',
        'company_size',
        'company_industries',
        'company_tagline',
        'company_founded_year',
        'company_funding_series',
        'company_investors',
        'company_headquarters_country',

        // Benefits and perks
        'retirement_plan',
        'generous_parental_leave',
        'visa_sponsorship',
        'relocation_assistance',
        'remote_work_available',
        'tuition_reimbursement',
        'generous_paid_time_off',

        // Work environment details
        'physical_environment',
        'oral_communication_level',
        'physical_labor_intensity',
        'computer_usage',
        'cognitive_demand',
        'security_clearance',

        // Data quality and tracking
        'estimated_publish_date',
        'is_expired',
        'data_quality_score',
        'last_scraped_at',
        'requisition_id',
        'collapse_key',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_compensation_transparent' => 'boolean',
        'is_expired' => 'boolean',
        'retirement_plan' => 'boolean',
        'generous_parental_leave' => 'boolean',
        'visa_sponsorship' => 'boolean',
        'relocation_assistance' => 'boolean',
        'remote_work_available' => 'boolean',
        'tuition_reimbursement' => 'boolean',
        'generous_paid_time_off' => 'boolean',
        'technical_tools' => 'array',
        'workplace_countries' => 'array',
        'workplace_states' => 'array',
        'workplace_cities' => 'array',
        'licenses_or_certifications' => 'array',
        'company_industries' => 'array',
        'company_investors' => 'array',
        'estimated_publish_date' => 'datetime',
        'last_scraped_at' => 'datetime',
    ];
}