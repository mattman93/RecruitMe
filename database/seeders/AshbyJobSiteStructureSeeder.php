<?php

namespace Database\Seeders;

use App\Models\JobSiteStructure;
use Illuminate\Database\Seeder;

class AshbyJobSiteStructureSeeder extends Seeder
{
    public function run(): void
    {
        JobSiteStructure::create([
            'domain' => 'jobs.ashbyhq.com',
            'platform_name' => 'Ashby',
            'site_pattern' => 'jobs.ashbyhq.com/*/application',
            
            'application_flow' => [
                'steps' => [
                    [
                        'step' => 1,
                        'action' => 'navigate',
                        'url_pattern' => 'jobs.ashbyhq.com/*/[job-id]',
                        'description' => 'Navigate to job posting page'
                    ],
                    [
                        'step' => 2,
                        'action' => 'click',
                        'selector' => 'button:has-text("Apply for this job")',
                        'description' => 'Click apply button to go to application form'
                    ],
                    [
                        'step' => 3,
                        'action' => 'fill_form',
                        'url_pattern' => 'jobs.ashbyhq.com/*/application',
                        'description' => 'Fill out application form'
                    ],
                    [
                        'step' => 4,
                        'action' => 'submit',
                        'selector' => 'button:has-text("Submit")',
                        'description' => 'Submit application'
                    ]
                ]
            ],
            
            'form_fields' => [
                'name' => [
                    'selectors' => ['input[name="name"]', '#name'],
                    'type' => 'text',
                    'required' => true,
                    'data_source' => 'personal.full_name'
                ],
                'email' => [
                    'selectors' => ['input[name="email"]', 'input[type="email"]'],
                    'type' => 'email',
                    'required' => true,
                    'data_source' => 'personal.email'
                ],
                'resume' => [
                    'selectors' => ['input[type="file"]', 'input[name*="resume"]'],
                    'type' => 'file',
                    'required' => true,
                    'data_source' => 'resume.file_path'
                ],
                'work_authorization' => [
                    'selectors' => ['input[name*="work_authorization"]', 'input[value*="yes"]'],
                    'type' => 'radio',
                    'required' => true,
                    'data_source' => 'work_authorization.can_work_in_us',
                    'question_text' => 'Do you have a legal authorization to work in the United States'
                ],
                'visa_sponsorship' => [
                    'selectors' => ['input[name*="visa"]', 'input[value*="no"]'],
                    'type' => 'radio',
                    'required' => true,
                    'data_source' => 'work_authorization.requires_visa_sponsorship',
                    'question_text' => 'Will you require a visa sponsorship'
                ],
                'location' => [
                    'selectors' => ['input[name*="location"]', 'select[name*="location"]'],
                    'type' => 'text',
                    'required' => false,
                    'data_source' => 'location.current_location'
                ]
            ],
            
            'button_selectors' => [
                'apply_button' => [
                    'selectors' => ['button:has-text("Apply for this job")', 'a:has-text("Apply")'],
                    'page' => 'job_posting'
                ],
                'submit_button' => [
                    'selectors' => ['button:has-text("Submit")', 'input[type="submit"]'],
                    'page' => 'application_form'
                ]
            ],
            
            'field_mappings' => [
                'name' => 'personal.full_name',
                'email' => 'personal.email',
                'resume' => 'resume.file_path',
                'work_authorization_yes' => 'work_authorization.can_work_in_us',
                'visa_sponsorship_no' => '!work_authorization.requires_visa_sponsorship'
            ],
            
            'validation_rules' => [
                'name' => 'required|min:2',
                'email' => 'required|email',
                'resume' => 'required|file|mimes:pdf,doc,docx'
            ],
            
            'dynamic_fields' => [
                'office_preference' => [
                    'condition' => 'location_type=hybrid',
                    'selectors' => ['input[name*="office"]'],
                    'type' => 'checkbox'
                ]
            ],
            
            'success_indicators' => [
                'selectors' => [
                    ':has-text("Thank you")',
                    ':has-text("Application submitted")',
                    ':has-text("Successfully submitted")',
                    '.confirmation-message'
                ],
                'url_patterns' => [
                    'jobs.ashbyhq.com/*/confirmation',
                    'jobs.ashbyhq.com/*/thank-you'
                ]
            ],
            
            'error_selectors' => [
                '.error',
                '.field-error',
                ':has-text("required")',
                ':has-text("invalid")'
            ],
            
            'has_captcha' => false,
            'captcha_type' => null,
            
            'anti_bot_measures' => [
                'rate_limiting' => true,
                'javascript_required' => true,
                'session_validation' => true
            ],
            
            'success_rate' => 85,
            'total_attempts' => 0,
            'automation_strategy' => 'semi_auto',
            
            'notes' => 'Ashby platform is generally automation-friendly. Uses standard form fields. May require user confirmation due to potential CAPTCHA on some clients.',
            
            'last_structure_update' => now(),
            'is_active' => true
        ]);
    }
}