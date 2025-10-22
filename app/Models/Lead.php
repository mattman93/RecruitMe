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

        // Form field discovery
        'discovered_fields',
        'fields_discovered_at',

        // Contact discovery
        'discovered_contacts',
        'discovered_contacts_at',
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
        'discovered_fields' => 'array',
        'fields_discovered_at' => 'datetime',
        'discovered_contacts' => 'array',
        'discovered_contacts_at' => 'datetime',
    ];

    /**
     * Get the job site structure for this lead's domain
     */
    public function jobSiteStructure()
    {
        $domain = $this->getDomain();
        if (!$domain) {
            return null;
        }

        return JobSiteStructure::where('domain', $domain)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Extract domain from source URL
     */
    public function getDomain(): ?string
    {
        if (!$this->source_url) {
            return null;
        }

        $parsed = parse_url($this->source_url);
        return $parsed['host'] ?? null;
    }

    /**
     * Check if this lead has auto-fill data available
     */
    public function hasAutoFillData(): bool
    {
        return $this->jobSiteStructure() !== null;
    }

    /**
     * Calculate dynamic token cost based on job quality metrics
     * Base cost: 40 tokens
     * Premium factors can increase up to 60+ tokens
     */
    public function calculateTokenCost(): int
    {
        $baseCost = 40;
        $premiumPoints = 0;

        // High salary range (+5 points)
        if ($this->yearly_min_compensation && $this->yearly_min_compensation >= 120000) {
            $premiumPoints += 5;
        }
        if ($this->yearly_min_compensation && $this->yearly_min_compensation >= 150000) {
            $premiumPoints += 3; // Extra for very high salary
        }

        // Remote work (+4 points)
        if ($this->remote_work_available || strtolower($this->workplace_type ?? '') === 'remote') {
            $premiumPoints += 4;
        }

        // Big tech companies (+6 points)
        $bigTechCompanies = [
            'google', 'meta', 'facebook', 'amazon', 'apple', 'microsoft', 'netflix',
            'tesla', 'nvidia', 'salesforce', 'oracle', 'adobe', 'uber', 'airbnb',
            'stripe', 'square', 'coinbase', 'databricks', 'snowflake', 'palantir',
            'openai', 'anthropic', 'spacex', 'twitter', 'x corp', 'linkedin'
        ];
        $companyLower = strtolower($this->company ?? '');
        foreach ($bigTechCompanies as $bigTech) {
            if (str_contains($companyLower, $bigTech)) {
                $premiumPoints += 6;
                break;
            }
        }

        // Senior/Staff/Principal roles (+4 points)
        $seniorKeywords = ['senior', 'staff', 'principal', 'lead', 'architect', 'director', 'vp'];
        $titleLower = strtolower($this->job_title ?? '');
        foreach ($seniorKeywords as $keyword) {
            if (str_contains($titleLower, $keyword)) {
                $premiumPoints += 4;
                break;
            }
        }

        // Well-funded startups - Series B+ (+3 points)
        $fundingSeries = strtolower($this->company_funding_series ?? '');
        if (in_array($fundingSeries, ['series b', 'series c', 'series d', 'series e', 'ipo', 'public'])) {
            $premiumPoints += 3;
        }

        // Generous benefits package (+2 points for 3+ benefits)
        $benefitsCount = 0;
        if ($this->retirement_plan) $benefitsCount++;
        if ($this->generous_parental_leave) $benefitsCount++;
        if ($this->visa_sponsorship) $benefitsCount++;
        if ($this->relocation_assistance) $benefitsCount++;
        if ($this->tuition_reimbursement) $benefitsCount++;
        if ($this->generous_paid_time_off) $benefitsCount++;

        if ($benefitsCount >= 3) {
            $premiumPoints += 2;
        }

        // Compensation transparency (+2 points)
        if ($this->is_compensation_transparent) {
            $premiumPoints += 2;
        }

        // Recent posting - within 7 days (+3 points)
        if ($this->estimated_publish_date && $this->estimated_publish_date->greaterThan(now()->subDays(7))) {
            $premiumPoints += 3;
        }

        // High data quality score (+2 points if score >= 80)
        if ($this->data_quality_score && $this->data_quality_score >= 80) {
            $premiumPoints += 2;
        }

        // Desirable locations (+3 points)
        $desirableLocations = ['san francisco', 'new york', 'seattle', 'austin', 'boston', 'palo alto', 'mountain view'];
        $locationLower = strtolower($this->location ?? '');
        foreach ($desirableLocations as $location) {
            if (str_contains($locationLower, $location)) {
                $premiumPoints += 3;
                break;
            }
        }

        return min($baseCost + $premiumPoints, 80); // Cap at 80 tokens
    }
}