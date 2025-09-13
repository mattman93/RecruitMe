<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DataSource>
 */
class DataSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sources = [
            [
                'name' => 'hiring.cafe',
                'url' => 'https://hiring.cafe/api/search-jobs',
                'fetch_type' => 'API',
                'description' => 'AI-powered job search engine with comprehensive job data',
            ],
            [
                'name' => 'indeed',
                'url' => 'https://indeed.com',
                'fetch_type' => 'SCRAPE',
                'description' => 'Popular job board with millions of listings',
            ],
            [
                'name' => 'linkedin',
                'url' => 'https://linkedin.com/jobs',
                'fetch_type' => 'SCRAPE',
                'description' => 'Professional networking platform with job listings',
            ],
        ];

        $source = $this->faker->randomElement($sources);
        
        return [
            'name' => $source['name'],
            'url' => $source['url'],
            'fetch_type' => $source['fetch_type'],
            'data_return_type' => 'json',
            'headers' => [
                'accept' => '*/*',
                'content-type' => 'application/json',
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36',
            ],
            'custom_data' => [
                'pagination' => true,
                'rate_limit' => 100,
                'timeout' => 30,
            ],
            'is_active' => true,
            'rate_limit_per_hour' => $this->faker->numberBetween(50, 200),
            'last_fetched_at' => $this->faker->optional(0.7)->dateTimeBetween('-7 days', 'now'),
            'total_jobs_fetched' => $this->faker->numberBetween(0, 5000),
            'description' => $source['description'],
        ];
    }

    /**
     * Create a hiring.cafe data source
     */
    public function hiringCafe(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'hiring.cafe',
            'url' => 'https://hiring.cafe/api/search-jobs',
            'fetch_type' => 'API',
            'data_return_type' => 'json',
            'headers' => [
                'authority' => 'hiring.cafe',
                'accept' => '*/*',
                'accept-encoding' => 'gzip, deflate, br, zstd',
                'accept-language' => 'en-US,en;q=0.9',
                'content-type' => 'application/json',
                'origin' => 'https://hiring.cafe',
                'referer' => 'https://hiring.cafe/',
                'sec-ch-ua' => '"Chromium";v="140", "Not=A?Brand";v="24", "Google Chrome";v="140"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"macOS"',
                'sec-fetch-dest' => 'empty',
                'sec-fetch-mode' => 'cors',
                'sec-fetch-site' => 'same-origin',
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36',
            ],
            'custom_data' => [
                'method' => 'POST',
                'default_payload' => [
                    'size' => 40,
                    'page' => 0,
                    'searchState' => [
                        'locations' => [
                            [
                                'formatted_address' => 'United States',
                                'types' => ['country'],
                                'geometry' => [
                                    'location' => [
                                        'lat' => '39.8023',
                                        'lon' => '-75.0641'
                                    ]
                                ],
                                'id' => 'user_country',
                                'address_components' => [
                                    [
                                        'long_name' => 'United States',
                                        'short_name' => 'US',
                                        'types' => ['country']
                                    ]
                                ],
                                'options' => [
                                    'flexible_regions' => ['anywhere_in_continent', 'anywhere_in_world']
                                ]
                            ]
                        ],
                        'workplaceTypes' => ['Remote', 'Hybrid', 'Onsite'],
                        'defaultToUserLocation' => true,
                        'userLocation' => null,
                        'dateFetchedPastNDays' => 30
                    ]
                ],
                'supports_pagination' => true,
                'max_results_per_request' => 100,
                'rate_limit_per_hour' => 100,
                'job_titles' => [
                    'Software Engineer',
                    'Frontend Developer',
                    'Backend Developer',
                    'Full Stack Developer',
                    'DevOps Engineer',
                    'Data Scientist',
                    'Product Manager',
                    'UX Designer',
                    'QA Engineer',
                    'Mobile Developer',
                    'Machine Learning Engineer',
                    'Cloud Engineer',
                    'Security Engineer',
                    'Site Reliability Engineer',
                    'Engineering Manager',
                    'Technical Lead',
                    'Software Architect',
                    'Data Engineer',
                    'Platform Engineer',
                    'AI Engineer'
                ],
            ],
            'rate_limit_per_hour' => 100,
            'description' => 'AI-powered job search engine with comprehensive job data and advanced filtering capabilities',
        ]);
    }
}
