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
        $employmentTypes = ['Full-time', 'Part-time', 'Contract', 'Remote'];

        return [
            'job_title' => $this->faker->randomElement($jobTitles),
            'company' => $this->faker->randomElement($companies),
            'pay_range' => '$' . $this->faker->numberBetween(80, 250) . 'k - $' . $this->faker->numberBetween(250, 400) . 'k',
            'description' => $this->faker->paragraph(3),
            'location' => $this->faker->randomElement($locations),
            'employment_type' => $this->faker->randomElement($employmentTypes),
            'experience_level' => $this->faker->randomElement($experienceLevels),
            'source_url' => $this->faker->url(),
            'is_active' => true,
        ];
    }
}