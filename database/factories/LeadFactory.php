<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LeadFactory extends Factory
{
    public function definition()
    {
        $jobTitles = [
            'Senior Software Engineer', 'Frontend Developer', 'Backend Developer',
            'Full Stack Developer', 'DevOps Engineer', 'Product Manager',
            'UI/UX Designer', 'Data Scientist', 'Machine Learning Engineer',
            'Software Architect', 'Technical Lead', 'QA Engineer',
            'Mobile Developer', 'Cloud Engineer', 'Security Engineer'
        ];

        $coreJobTitles = [
            'Software Engineer', 'Frontend Developer', 'Backend Developer',
            'Full Stack Developer', 'DevOps Engineer', 'Product Manager',
            'Designer', 'Data Scientist', 'ML Engineer', 'Architect'
        ];

        $companies = [
            'Google', 'Microsoft', 'Amazon', 'Apple', 'Meta', 'Netflix',
            'Stripe', 'Airbnb', 'Uber', 'Spotify', 'Slack', 'Dropbox',
            'GitHub', 'Atlassian', 'Salesforce', 'Adobe', 'Tesla', 'SpaceX'
        ];

        $locations = [
            'San Francisco, CA', 'New York, NY', 'Seattle, WA', 'Austin, TX',
            'Boston, MA', 'Los Angeles, CA', 'Chicago, IL', 'Remote',
            'Denver, CO', 'Portland, OR'
        ];

        $experienceLevels = ['Entry Level', 'Mid Level', 'Senior Level', 'Lead Level'];
        $seniorityLevels = ['No Prior Experience Required', 'Entry Level', 'Mid Level', 'Senior Level'];
        $employmentTypes = ['Full-time', 'Part-time', 'Contract', 'Remote'];
        $workplaceTypes = ['Remote', 'Hybrid', 'Onsite'];
        $roleTypes = ['Individual Contributor', 'People Manager'];
        $jobCategories = ['Software Development', 'Engineering', 'Product', 'Design', 'Data Science'];
        $degreeRequirements = ['Required', 'Preferred', 'Not Mentioned'];
        $technicalTools = [
            ['React', 'Node.js', 'TypeScript'],
            ['Python', 'Django', 'PostgreSQL'],
            ['AWS', 'Docker', 'Kubernetes'],
            ['React Native', 'Swift', 'Kotlin'],
            ['Java', 'Spring Boot', 'MySQL']
        ];
        $physicalEnvironments = ['Office', 'Outdoor', 'Vehicle', 'Industrial', 'Customer-Facing'];
        $levels = ['Low', 'Medium', 'High'];
        $securityClearances = ['None', 'Confidential', 'Secret', 'Top Secret'];
        $fundingSeries = ['Seed', 'Series A', 'Series B', 'Series C', 'IPO'];
        $industries = [
            ['Technology', 'Software'],
            ['Healthcare', 'Medical'],
            ['Finance', 'Fintech'],
            ['E-commerce', 'Retail'],
            ['Education', 'EdTech']
        ];

        $yearlyMin = $this->faker->numberBetween(80000, 200000);
        $yearlyMax = $yearlyMin + $this->faker->numberBetween(20000, 100000);
        
        return [
            // Original fields
            'job_title' => $this->faker->randomElement($jobTitles),
            'company' => $this->faker->randomElement($companies),
            'pay_range' => '$' . number_format($yearlyMin) . ' - $' . number_format($yearlyMax),
            'description' => $this->faker->paragraph(3),
            'location' => $this->faker->randomElement($locations),
            'employment_type' => $this->faker->randomElement($employmentTypes),
            'experience_level' => $this->faker->randomElement($experienceLevels),
            'source_url' => $this->faker->url(),
            'is_active' => true,

            // Job identification and source
            'external_id' => 'hiring_cafe_' . $this->faker->uuid(),
            'source_platform' => 'hiring.cafe',
            'board_token' => $this->faker->slug(2),
            'apply_url' => $this->faker->url(),

            // Enhanced job information
            'core_job_title' => $this->faker->randomElement($coreJobTitles),
            'job_title_raw' => $this->faker->randomElement($jobTitles),
            'requirements_summary' => $this->faker->sentence(10),
            'technical_tools' => json_encode($this->faker->randomElement($technicalTools)),
            'job_category' => $this->faker->randomElement($jobCategories),
            'seniority_level' => $this->faker->randomElement($seniorityLevels),
            'role_type' => $this->faker->randomElement($roleTypes),

            // Enhanced compensation
            'yearly_min_compensation' => $yearlyMin,
            'yearly_max_compensation' => $yearlyMax,
            'hourly_min_compensation' => round($yearlyMin / 2080, 2),
            'hourly_max_compensation' => round($yearlyMax / 2080, 2),
            'listed_compensation_currency' => 'USD',
            'listed_compensation_frequency' => 'Yearly',
            'is_compensation_transparent' => $this->faker->boolean(70),

            // Work arrangements and location
            'workplace_type' => $this->faker->randomElement($workplaceTypes),
            'workplace_countries' => json_encode(['US']),
            'workplace_states' => json_encode([$this->faker->state()]),
            'workplace_cities' => json_encode([$this->faker->city()]),
            'formatted_workplace_location' => $this->faker->city() . ', ' . $this->faker->stateAbbr() . ', United States',

            // Requirements and qualifications
            'min_industry_and_role_yoe' => $this->faker->numberBetween(0, 10),
            'bachelors_degree_requirement' => $this->faker->randomElement($degreeRequirements),
            'masters_degree_requirement' => $this->faker->randomElement($degreeRequirements),
            'doctorate_degree_requirement' => $this->faker->randomElement($degreeRequirements),
            'licenses_or_certifications' => json_encode([]),

            // Company information
            'company_website' => $this->faker->domainName(),
            'company_linkedin_url' => 'linkedin.com/company/' . $this->faker->slug(2),
            'company_size' => $this->faker->numberBetween(10, 5000),
            'company_industries' => json_encode($this->faker->randomElement($industries)),
            'company_tagline' => $this->faker->sentence(6),
            'company_founded_year' => $this->faker->numberBetween(1990, 2020),
            'company_funding_series' => $this->faker->randomElement($fundingSeries),
            'company_investors' => json_encode([$this->faker->company(), $this->faker->company()]),
            'company_headquarters_country' => 'United States',

            // Benefits and perks
            'retirement_plan' => $this->faker->boolean(60),
            'generous_parental_leave' => $this->faker->boolean(40),
            'visa_sponsorship' => $this->faker->boolean(30),
            'relocation_assistance' => $this->faker->boolean(25),
            'remote_work_available' => $this->faker->boolean(70),
            'tuition_reimbursement' => $this->faker->boolean(35),
            'generous_paid_time_off' => $this->faker->boolean(50),

            // Work environment details
            'physical_environment' => $this->faker->randomElement($physicalEnvironments),
            'oral_communication_level' => $this->faker->randomElement($levels),
            'physical_labor_intensity' => $this->faker->randomElement($levels),
            'computer_usage' => $this->faker->randomElement($levels),
            'cognitive_demand' => $this->faker->randomElement($levels),
            'security_clearance' => $this->faker->randomElement($securityClearances),

            // Data quality and tracking
            'estimated_publish_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'is_expired' => $this->faker->boolean(10),
            'data_quality_score' => $this->faker->randomElement(['high', 'medium', 'low']),
            'last_scraped_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'requisition_id' => $this->faker->uuid(),
            'collapse_key' => $this->faker->sha256(),
        ];
    }
}