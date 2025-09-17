<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobSiteStructure;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class JobApplicationService
{
    /**
     * Determine the best application strategy for a job
     */
    public function determineApplicationStrategy(Lead $lead): array
    {
        $domain = $this->extractDomainFromUrl($lead->source_url);
        $siteStructure = JobSiteStructure::where('domain', $domain)
            ->where('is_active', true)
            ->first();
        
        if (!$siteStructure) {
            // For unknown sites, defer analysis to batch processing to avoid timeouts
            return [
                'strategy' => 'semi_auto', // Default strategy for unknown sites
                'reason' => 'Unknown site - analysis will be performed during processing',
                'site_structure' => null,
                'requires_analysis' => true
            ];
        }
        
        // Determine strategy based on site capabilities and success rate
        $strategy = $siteStructure->automation_strategy;
        
        if ($siteStructure->has_captcha && $strategy === 'full_auto') {
            $strategy = 'semi_auto'; // Downgrade due to CAPTCHA
        }
        
        if ($siteStructure->success_rate < 60 && $strategy === 'full_auto') {
            $strategy = 'semi_auto'; // Downgrade due to low success rate
        }
        
        return [
            'strategy' => $strategy,
            'reason' => $this->getStrategyReason($siteStructure),
            'site_structure' => $siteStructure
        ];
    }
    
    /**
     * Queue a job application
     */
    public function queueApplication(User $user, Lead $lead, array $customResponses = []): JobApplication
    {
        $strategy = $this->determineApplicationStrategy($lead);
        
        // Get user's application data
        $formData = $this->prepareApplicationData($user, $lead, $customResponses);
        
        $application = JobApplication::create([
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'job_site_structure_id' => $strategy['site_structure']?->id,
            'application_method' => $strategy['strategy'],
            'form_data_sent' => $formData,
            'custom_responses' => $customResponses,
            'status' => 'queued',
            'queued_at' => now(),
            'automation_log' => "Application queued with strategy: {$strategy['strategy']}. Reason: {$strategy['reason']}"
        ]);
        
        Log::info("Job application queued", [
            'application_id' => $application->id,
            'user_id' => $user->id,
            'lead_id' => $lead->id,
            'strategy' => $strategy['strategy']
        ]);
        
        return $application;
    }
    
    /**
     * Process a queued application
     */
    public function processApplication(JobApplication $application): bool
    {
        $application->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);
        
        $this->logStep($application, "Starting application process");
        
        // Perform site analysis if needed (for unknown sites)
        if (!$application->jobSiteStructure) {
            $this->analyzeSiteStructureIfNeeded($application);
        }
        
        try {
            switch ($application->application_method) {
                case 'full_auto':
                    return $this->processFullAutomation($application);
                
                case 'semi_auto':
                    return $this->processSemiAutomation($application);
                
                case 'iframe':
                    return $this->processIframeApplication($application);
                
                default:
                    throw new \Exception("Unsupported application method: {$application->application_method}");
            }
        } catch (\Exception $e) {
            $this->handleApplicationFailure($application, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Full automation using Playwright
     */
    protected function processFullAutomation(JobApplication $application): bool
    {
        $this->logStep($application, "Attempting full automation with Playwright");
        
        // This will use the PlaywrightAutomationService
        $automationService = new PlaywrightAutomationService();
        
        $result = $automationService->fillAndSubmitApplication(
            $application->lead,
            $application->jobSiteStructure,
            $application->form_data_sent,
            $application
        );
        
        if ($result['success']) {
            $application->update([
                'status' => 'submitted',
                'completed_at' => now(),
                'final_application_url' => $result['final_url'] ?? null,
                'confirmation_number' => $result['confirmation_number'] ?? null,
                'confirmation_message' => $result['confirmation_message'] ?? null,
                'automation_duration_seconds' => $result['duration'] ?? null
            ]);
            
            $this->logStep($application, "Application submitted successfully via full automation");
            return true;
        } else {
            // Fallback to semi-auto if full automation fails
            $this->logStep($application, "Full automation failed, falling back to semi-auto: " . $result['error']);
            $application->update(['application_method' => 'semi_auto']);
            return $this->processSemiAutomation($application);
        }
    }
    
    /**
     * Semi-automation - fill form, let user submit
     */
    protected function processSemiAutomation(JobApplication $application): bool
    {
        $this->logStep($application, "Starting semi-automation process");
        
        // Use Playwright to navigate and fill form, but stop before submission
        $automationService = new PlaywrightAutomationService();
        
        $result = $automationService->fillFormOnly(
            $application->lead,
            $application->jobSiteStructure,
            $application->form_data_sent,
            $application
        );
        
        if ($result['success']) {
            $application->update([
                'status' => 'form_filled',
                'final_application_url' => $result['final_url'],
                'playwright_session_data' => $result['session_data'] ?? null
            ]);
            
            $this->logStep($application, "Form filled successfully, awaiting user submission");
            
            // Here we would notify the user or present them with the filled form
            $this->notifyUserForSubmission($application);
            return true;
        } else {
            $this->logStep($application, "Semi-automation failed: " . $result['error']);
            return false;
        }
    }
    
    /**
     * iframe-based application for complex sites
     */
    protected function processIframeApplication(JobApplication $application): bool
    {
        $this->logStep($application, "Starting iframe-based application");
        
        // Generate a secure session for iframe application
        $sessionToken = Str::uuid();
        
        $application->update([
            'status' => 'form_filled',
            'final_application_url' => $application->lead->source_url,
            'playwright_session_data' => [
                'iframe_session_token' => $sessionToken,
                'prepared_data' => $application->form_data_sent
            ]
        ]);
        
        $this->logStep($application, "iframe application prepared, user can now complete application");
        
        // Notify user that iframe application is ready
        $this->notifyUserForIframeApplication($application, $sessionToken);
        return true;
    }
    
    /**
     * Prepare user data for application forms
     */
    public function prepareApplicationData(User $user, Lead $lead, array $customResponses = []): array
    {
        // Get user's work experience and resume data
        $workExperience = $user->workExperience()->orderBy('start_date', 'desc')->get();
        $resume = $user->uploadedFiles()->where('file_type', 'resume')->latest()->first();
        
        // Split name if no separate first/last name fields
        $nameParts = explode(' ', $user->name, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        $baseData = [
            'personal' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'linkedin_url' => $user->linkedin_url ?? null,
                'portfolio_url' => $user->portfolio_url ?? null
            ],
            'resume' => [
                'file_path' => $resume?->file_path,
                'file_name' => $resume?->original_name
            ],
            'work_authorization' => [
                'can_work_in_us' => true, // This should come from user profile
                'requires_visa_sponsorship' => false // This should come from user profile
            ],
            'location' => [
                'current_location' => $user->location ?? 'Not specified',
                'willing_to_relocate' => true, // This should come from user profile
                'remote_work_preference' => 'hybrid' // This should come from user profile
            ],
            'experience' => $workExperience->map(function ($exp) {
                return [
                    'company' => $exp->company,
                    'position' => $exp->position,
                    'start_date' => $exp->start_date,
                    'end_date' => $exp->end_date,
                    'is_current' => $exp->is_current,
                    'description' => $exp->description,
                    'achievements' => $exp->achievements
                ];
            })->toArray(),
            'custom' => $customResponses
        ];
        
        return $baseData;
    }
    
    /**
     * Extract domain from URL
     */
    protected function extractDomainFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }
    
    /**
     * Get human-readable reason for strategy choice
     */
    protected function getStrategyReason(JobSiteStructure $siteStructure): string
    {
        if ($siteStructure->has_captcha) {
            return "Site has CAPTCHA - semi-automation required";
        }
        
        if ($siteStructure->success_rate < 60) {
            return "Low success rate ({$siteStructure->success_rate}%) - using semi-automation for reliability";
        }
        
        return match($siteStructure->automation_strategy) {
            'full_auto' => "Site fully compatible with automation",
            'semi_auto' => "Site requires user confirmation for submission",
            'manual_only' => "Site requires manual application",
            default => "Unknown automation strategy"
        };
    }
    
    /**
     * Log application step
     */
    protected function logStep(JobApplication $application, string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}";
        
        $existingLog = $application->automation_log ?? '';
        $application->update([
            'automation_log' => $existingLog . "\n" . $logEntry
        ]);
        
        Log::info("Application step", [
            'application_id' => $application->id,
            'message' => $message
        ]);
    }
    
    /**
     * Analyze site structure if needed for unknown sites
     */
    protected function analyzeSiteStructureIfNeeded(JobApplication $application): void
    {
        $lead = $application->lead;
        $domain = $this->extractDomainFromUrl($lead->source_url);
        
        // Check if we already have structure for this domain
        $existingStructure = JobSiteStructure::where('domain', $domain)->first();
        if ($existingStructure) {
            $application->update(['job_site_structure_id' => $existingStructure->id]);
            return;
        }
        
        $this->logStep($application, "Analyzing site structure for unknown domain: {$domain}");
        
        try {
            $analysisService = app(SiteStructureAnalysisService::class);
            $analysisResult = $analysisService->analyzeJobSite($lead);
            
            if ($analysisResult['success']) {
                $siteStructure = JobSiteStructure::create([
                    'domain' => $domain,
                    'platform_type' => $analysisResult['platform_type'],
                    'form_selectors' => $analysisResult['form_selectors'] ?? [],
                    'apply_button_selectors' => $analysisResult['apply_button_selectors'] ?? [],
                    'field_mappings' => $analysisResult['field_mappings'] ?? [],
                    'navigation_flow' => $analysisResult['navigation_flow'] ?? [],
                    'last_analyzed_at' => now(),
                    'is_active' => true
                ]);
                
                $application->update(['job_site_structure_id' => $siteStructure->id]);
                $this->logStep($application, "Site structure analysis completed for {$domain}");
            } else {
                $this->logStep($application, "Site structure analysis failed: " . ($analysisResult['error'] ?? 'Unknown error'));
                
                // Create placeholder structure for manual processing
                $siteStructure = JobSiteStructure::create([
                    'domain' => $domain,
                    'platform_type' => 'unknown',
                    'form_selectors' => [],
                    'apply_button_selectors' => [],
                    'field_mappings' => [],
                    'navigation_flow' => [],
                    'last_analyzed_at' => now(),
                    'is_active' => false,
                    'analysis_failed' => true
                ]);
                
                $application->update([
                    'job_site_structure_id' => $siteStructure->id,
                    'application_method' => 'manual_required'
                ]);
            }
        } catch (\Exception $e) {
            $this->logStep($application, "Site analysis error: " . $e->getMessage());
            Log::error("Site structure analysis failed", [
                'domain' => $domain,
                'url' => $lead->source_url,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle application failure
     */
    protected function handleApplicationFailure(JobApplication $application, string $error): void
    {
        $application->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $error,
            'retry_count' => $application->retry_count + 1
        ]);
        
        $this->logStep($application, "Application failed: {$error}");
        
        // If we haven't exceeded retry limit, we could queue for retry
        if ($application->retry_count < 3) {
            // Could implement retry logic here
        }
    }
    
    /**
     * Notify user for manual submission
     */
    protected function notifyUserForSubmission(JobApplication $application): void
    {
        // This would trigger a notification to the user
        // Could be email, websocket, push notification, etc.
        Log::info("User notification needed for application submission", [
            'application_id' => $application->id,
            'user_id' => $application->user_id
        ]);
    }
    
    /**
     * Notify user for iframe application
     */
    protected function notifyUserForIframeApplication(JobApplication $application, string $sessionToken): void
    {
        Log::info("iframe application ready for user", [
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'session_token' => $sessionToken
        ]);
    }
}