<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class HiringCafeDataSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if hiring.cafe data source already exists
        $existingDataSource = DataSource::where('name', 'hiring.cafe')->first();

        if ($existingDataSource) {
            Log::info('hiring.cafe data source already exists. Updating configuration...');

            $existingDataSource->update([
                'url' => 'https://hiring.cafe/api/search-jobs',
                'fetch_type' => 'API',
                'data_return_type' => 'json',
                'headers' => $this->getHeaders(),
                'custom_data' => $this->getCustomData(),
                'is_active' => true,
                'rate_limit_per_hour' => 100,
                'description' => 'AI-powered job search engine with comprehensive job data and advanced filtering capabilities',
            ]);

            $this->command->info('✓ Updated existing hiring.cafe data source');
        } else {
            DataSource::create([
                'name' => 'hiring.cafe',
                'url' => 'https://hiring.cafe/api/search-jobs',
                'fetch_type' => 'API',
                'data_return_type' => 'json',
                'headers' => $this->getHeaders(),
                'custom_data' => $this->getCustomData(),
                'is_active' => true,
                'rate_limit_per_hour' => 100,
                'description' => 'AI-powered job search engine with comprehensive job data and advanced filtering capabilities',
            ]);

            $this->command->info('✓ Created hiring.cafe data source');
        }
    }

    /**
     * Get the headers configuration for hiring.cafe API
     */
    private function getHeaders(): array
    {
        return [
            'accept' => '*/*',
            'origin' => 'https://hiring.cafe',
            'referer' => 'https://hiring.cafe/',
            'authority' => 'hiring.cafe',
            'sec-ch-ua' => '"Chromium";v="140", "Not=A?Brand";v="24", "Google Chrome";v="140"',
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
            'content-type' => 'application/json',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-origin',
            'accept-encoding' => 'gzip, deflate, br, zstd',
            'accept-language' => 'en-US,en;q=0.9',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"macOS"',
        ];
    }

    /**
     * Get the custom data configuration for hiring.cafe API
     */
    private function getCustomData(): array
    {
        return [
            'method' => 'POST',
            'job_titles' => [
                'Software Engineer',
                'Frontend Developer',
                'Backend Developer',
                'Full Stack Developer',
                'PHP Developer',
                'JavaScript Developer',
                'React Developer',
                'Node.js Developer',
                'Python Developer',
                'Java Developer',
                'C++ Developer',
                'Go Developer',
                'Rust Developer',
                'DevOps Engineer',
                'Site Reliability Engineer',
                'Data Engineer',
                'Machine Learning Engineer',
                'AI Engineer',
                'Cloud Engineer',
                'Mobile Developer',
                'iOS Developer',
                'Android Developer',
                'Flutter Developer',
                'Unity Developer',
                'Game Developer',
                'Blockchain Developer',
                'Smart Contract Developer',
                'Security Engineer',
                'Cybersecurity Analyst',
                'Penetration Tester',
                'Database Administrator',
                'Systems Administrator',
                'Network Engineer',
                'Infrastructure Engineer',
                'Platform Engineer',
                'Release Engineer',
                'Build Engineer',
                'QA Engineer',
                'Test Automation Engineer',
                'Performance Engineer',
                'MLOps Engineer',
                'DataOps Engineer',
                'Kubernetes Engineer',
                'Terraform Engineer',
                'AWS Engineer',
                'Azure Engineer',
                'GCP Engineer',
                'Data Scientist',
                'Data Analyst',
                'Business Intelligence Analyst',
                'Analytics Engineer',
                'Research Scientist',
                'Quantitative Analyst',
                'Statistician',
                'Product Manager',
                'Technical Product Manager',
                'UX Designer',
                'UI Designer',
                'Product Designer',
                'Design System Engineer',
                'Engineering Manager',
                'Technical Lead',
                'Staff Engineer',
                'Principal Engineer',
                'Architect',
                'Solutions Architect',
                'Enterprise Architect',
                'CTO',
                'VP Engineering',
                'Sales Engineer',
                'Solutions Engineer',
                'Customer Success Engineer',
                'Technical Writer',
                'Developer Relations',
                'Developer Advocate',
                'Technical Consultant',
                'Implementation Specialist',
                'Integration Specialist',
                'Technical Support Engineer',
                'Co-founder',
                'Technical Co-founder',
                'CTO Co-founder',
                'Remote Developer',
                'Freelance Developer',
                'Contract Developer',
                'Consultant',
            ],
            'default_payload' => [
                'page' => 0,
                'size' => 40,
                'searchState' => [
                    'locations' => [
                        [
                            'id' => 'user_country',
                            'types' => ['country'],
                            'options' => [
                                'flexible_regions' => ['anywhere_in_continent', 'anywhere_in_world'],
                            ],
                            'geometry' => [
                                'location' => [
                                    'lat' => '39.8023',
                                    'lon' => '-75.0641',
                                ],
                            ],
                            'formatted_address' => 'United States',
                            'address_components' => [
                                [
                                    'types' => ['country'],
                                    'long_name' => 'United States',
                                    'short_name' => 'US',
                                ],
                            ],
                        ],
                    ],
                    'userLocation' => null,
                    'workplaceTypes' => ['Remote', 'Hybrid', 'Onsite'],
                    'dateFetchedPastNDays' => 30,
                    'defaultToUserLocation' => true,
                ],
            ],
            'rate_limit_per_hour' => 100,
            'supports_pagination' => true,
            'max_results_per_request' => 100,
        ];
    }
}
