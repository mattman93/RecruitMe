<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobSiteStructure;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserFormPreference;
use App\Models\DiscoveredContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;
use SendGrid\Mail\Mail;
use SendGrid;

class JobApplicationService
{
    /**
     * Determine the best application strategy for a job - now defaults to email-based
     */
    public function determineApplicationStrategy(Lead $lead): array
    {
        // All applications now use email-based strategy
        return [
            'strategy' => 'email_based',
            'reason' => 'Email-based application for better deliverability and reduced bot detection',
            'site_structure' => null
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
     * Process a queued application using email-based approach
     */
    public function processApplication(JobApplication $application): bool
    {
        $application->update([
            'status' => 'in_progress',
            'started_at' => now()
        ]);

        $this->logStep($application, "Starting email-based application process");

        try {
            // Discover contact information
            $contactEmails = $this->discoverContactEmails($application->lead);

            // Generate application email content
            $emailContent = $this->generateApplicationEmail($application);

            // Send application emails
            $emailsSent = $this->sendApplicationEmails($application, $contactEmails, $emailContent);

            if ($emailsSent > 0) {
                $application->update([
                    'status' => 'submitted',
                    'completed_at' => now(),
                    'confirmation_message' => "Application email sent to {$emailsSent} contact(s)",
                    'final_application_url' => null // No longer applicable for email-based applications
                ]);

                $this->logStep($application, "Application email sent successfully to {$emailsSent} contact(s)");
                return true;
            } else {
                throw new \Exception("Failed to send application emails");
            }

        } catch (\Exception $e) {
            $this->handleApplicationFailure($application, $e->getMessage());
            return false;
        }
    }

    /**
     * Discover contact emails for a job lead using cache-first approach
     */
    protected function discoverContactEmails(Lead $lead): array
    {
        $domain = $this->extractDomainFromUrl($lead->source_url);
        $companyName = $lead->company ?? $domain;

        Log::info("Discovering contact emails for domain: {$domain}");

        // First, check if we have cached contacts for this lead or domain
        $cachedContacts = DiscoveredContact::getCachedContacts($lead, $domain);

        if (!empty($cachedContacts)) {
            Log::info("Using cached contact emails", [
                'domain' => $domain,
                'contacts' => count($cachedContacts)
            ]);

            // Update use count for cached contacts
            DiscoveredContact::where('lead_id', $lead->id)
                ->orWhere('company_domain', $domain)
                ->get()
                ->each(function ($contact) {
                    $contact->markAsUsed();
                });

            return $cachedContacts;
        }

        // No cached contacts found, use OpenAI to discover new ones
        Log::info("No cached contacts found, using OpenAI for discovery");

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Given a company domain '{$domain}', find all possible recruiting or HR-related emails (including likely patterns). Output as JSON array with 'email' and 'type' fields. Include common patterns like hr@, careers@, recruiting@, jobs@, talent@, etc. Do not include generic emails like info@ or support@."
                    ]
                ],
                'max_tokens' => 500,
                'temperature' => 0.1
            ]);

            $content = $response->choices[0]->message->content;
            $contacts = json_decode($content, true);

            if (is_array($contacts) && !empty($contacts)) {
                Log::info("Found contact emails via OpenAI", [
                    'domain' => $domain,
                    'contacts' => $contacts,
                    'api_cost_estimate' => '$0.003-0.006'
                ]);

                // Cache the discovered contacts
                DiscoveredContact::storeContacts($lead, $domain, $companyName, $contacts);

                return $contacts;
            }
        } catch (\Exception $e) {
            Log::warning("OpenAI contact discovery failed: " . $e->getMessage());
        }

        // Fallback to default careers email
        $defaultEmail = "careers@{$domain}";
        $fallbackContacts = [
            ['email' => $defaultEmail, 'type' => 'careers']
        ];

        Log::info("Using default contact email", ['email' => $defaultEmail]);

        // Cache the fallback contact as well
        DiscoveredContact::storeContacts($lead, $domain, $companyName, $fallbackContacts);

        return $fallbackContacts;
    }

    /**
     * Generate application email content based on job and user data
     */
    protected function generateApplicationEmail(JobApplication $application): array
    {
        $user = $application->user;
        $lead = $application->lead;
        $formData = $application->form_data_sent;

        // Get user's resume file
        $resume = $user->uploadedFiles()->where('file_type', 'resume')->latest()->first();

        // Generate subject line
        $subject = "Application for {$lead->title} - {$formData['personal']['full_name']}";

        // Generate personalized value proposition
        $valueProposition = $this->generateValueProposition($lead, $formData, $application->user);

        // Generate email body using form data and user preferences
        $contactEmails = $this->discoverContactEmails($lead);
        $body = $this->generateEmailBody($lead, $formData, $application->custom_responses, $contactEmails, $valueProposition);

        return [
            'subject' => $subject,
            'body' => $body,
            'resume_path' => $resume?->file_path,
            'resume_name' => $resume?->original_name ?? 'resume.pdf'
        ];
    }

    /**
     * Generate personalized value proposition using OpenAI
     */
    protected function generateValueProposition(Lead $lead, array $formData, User $user): string
    {
        // Get user's parsed resume for richer content
        $parsedResume = $user->parsedResumes()->latest()->first();
        $experience = $formData['experience'];

        try {
            // Build context for value proposition
            $jobContext = "Position: {$lead->title}";
            if ($lead->company) {
                $jobContext .= " at {$lead->company}";
            }
            if ($lead->description) {
                $jobContext .= "\nJob Description: " . substr($lead->description, 0, 1000);
            }

            $resumeContext = "";
            if ($parsedResume && $parsedResume->raw_text) {
                $resumeContext = "Resume Summary: " . substr($parsedResume->raw_text, 0, 1500);
            } else if (!empty($experience)) {
                $resumeContext = "Current Role: {$experience[0]['position']} at {$experience[0]['company']}";
                if (isset($experience[0]['description'])) {
                    $resumeContext .= "\nDescription: " . substr($experience[0]['description'], 0, 500);
                }
            }

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini', // Use cheaper model for this task
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Create a compelling 2-3 sentence value proposition for this job application. Focus on specific skills/experience that match the role requirements. Be confident but professional.\n\n{$jobContext}\n\n{$resumeContext}\n\nValue Proposition:"
                    ]
                ],
                'max_tokens' => 80,
                'temperature' => 0.7
            ]);

            $valueProposition = trim($response->choices[0]->message->content);

            Log::info("Generated value proposition", [
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'estimated_cost' => '$0.005-0.015',
                'model' => 'gpt-4o-mini'
            ]);

            return $valueProposition;

        } catch (\Exception $e) {
            Log::warning("Value proposition generation failed: " . $e->getMessage());

            // Fallback to generic statement
            $currentRole = $experience[0] ?? null;
            if ($currentRole) {
                return "My experience as {$currentRole['position']} at {$currentRole['company']} has prepared me well for this {$lead->title} role. I'm excited to bring my proven track record to your team.";
            }

            return "I'm excited about the opportunity to contribute to your team and believe my background makes me a strong candidate for this {$lead->title} position.";
        }
    }

    /**
     * Generate the email body content
     */
    protected function generateEmailBody(Lead $lead, array $formData, array $customResponses, array $discoveredContacts = [], string $valueProposition = ''): string
    {
        $personal = $formData['personal'];
        $experience = $formData['experience'];

        $body = "";

        // Add discovered contacts list at the top if provided
        if (!empty($discoveredContacts)) {
            $body .= "DISCOVERED CONTACTS FOR THIS APPLICATION:\n";
            foreach ($discoveredContacts as $contact) {
                $body .= "- {$contact['email']} ({$contact['type']})\n";
            }
            $body .= "\n---\n\n";
        }

        $body .= "Dear Hiring Manager,\n\n";
        $body .= "I am writing to express my strong interest in the {$lead->title} position";

        if (!empty($lead->company)) {
            $body .= " at {$lead->company}";
        }

        $body .= ". Please find my resume attached for your review.\n\n";

        // Add personalized value proposition if available
        if (!empty($valueProposition)) {
            $body .= $valueProposition . "\n\n";
        } else {
            // Fallback experience summary
            if (!empty($experience)) {
                $currentRole = $experience[0] ?? null;
                if ($currentRole) {
                    $body .= "I currently work as {$currentRole['position']} at {$currentRole['company']}, ";
                    $body .= "bringing valuable experience in this field.\n\n";
                }
            }
        }

        // Add work authorization and common preferences proactively
        $body .= $this->addCommonApplicationInfo($formData, $customResponses);

        // Add custom responses if any were collected
        if (!empty($customResponses)) {
            $body .= "Additional information:\n\n";
            foreach ($customResponses as $question => $answer) {
                $body .= "• {$question}: {$answer}\n";
            }
            $body .= "\n";
        }

        // Contact information
        $body .= "I am available for an interview at your convenience and can be reached at:\n";
        $body .= "• Email: {$personal['email']}\n";

        if (!empty($personal['phone'])) {
            $body .= "• Phone: {$personal['phone']}\n";
        }

        if (!empty($personal['linkedin_url'])) {
            $body .= "• LinkedIn: {$personal['linkedin_url']}\n";
        }

        $body .= "\nThank you for considering my application. I look forward to hearing from you.\n\n";
        $body .= "Best regards,\n{$personal['full_name']}";

        return $body;
    }

    /**
     * Add common application information from user preferences
     */
    protected function addCommonApplicationInfo(array $formData, array $customResponses): string
    {
        $info = "";
        $userId = auth()->id();

        if (!$userId) {
            return $info;
        }

        // Get common preferences that are relevant for email applications
        $commonPreferences = UserFormPreference::where('user_id', $userId)
            ->whereIn('field_identifier', [
                'work_authorization',
                'visa_sponsorship',
                'willing_to_relocate',
                'remote_work'
            ])
            ->orderBy('use_count', 'desc')
            ->get();

        if ($commonPreferences->isNotEmpty()) {
            $info .= "Key qualifications:\n\n";

            foreach ($commonPreferences as $pref) {
                $responseText = $this->formatPreferenceResponse($pref);
                if ($responseText) {
                    $info .= "• {$responseText}\n";
                }
            }
            $info .= "\n";
        }

        return $info;
    }

    /**
     * Format preference response for email inclusion
     */
    protected function formatPreferenceResponse(UserFormPreference $preference): ?string
    {
        $responseData = $preference->response_data;

        switch ($preference->field_identifier) {
            case 'work_authorization':
                return $responseData ? "Authorized to work in the United States" : null;

            case 'visa_sponsorship':
                return $responseData ? "Requires visa sponsorship" : "Does not require visa sponsorship";

            case 'willing_to_relocate':
                return $responseData ? "Willing to relocate for the position" : "Prefers to work in current location";

            case 'remote_work':
                if (is_array($responseData)) {
                    return "Remote work preference: " . implode(', ', $responseData);
                }
                return $responseData ? "Open to remote work opportunities" : null;

            default:
                // For other preferences, include them if they provide useful context
                if (is_string($responseData) && !empty($responseData)) {
                    return $responseData;
                }
                return null;
        }
    }

    /**
     * Send application emails to discovered contacts
     */
    protected function sendApplicationEmails(JobApplication $application, array $contacts, array $emailContent): int
    {
        $emailsSent = 0;
        $user = $application->user;

        // Check if user has Google OAuth and wants to send from their email
        $sendAsUser = $this->shouldSendAsUser($user);

        if ($sendAsUser) {
            return $this->sendViaUserEmail($application, $contacts, $emailContent);
        } else {
            return $this->sendViaAppliFlow($application, $contacts, $emailContent);
        }
    }

    /**
     * Check if we should send as user (via their Gmail) or as AppliFlow
     */
    protected function shouldSendAsUser(User $user): bool
    {
        // For now, check if user has Google OAuth connected
        // Later, this will check user preference from database/session
        return !empty($user->google_id);
    }

    /**
     * Send via user's Gmail account (Phase 2 implementation)
     */
    protected function sendViaUserEmail(JobApplication $application, array $contacts, array $emailContent): int
    {
        $user = $application->user;

        Log::info("Would send via user's Gmail account", [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'contacts' => count($contacts)
        ]);

        // TODO: Implement Gmail API sending
        // For now, simulate success
        return count($contacts);
    }

    /**
     * Send via AppliFlow (SendGrid) - current implementation
     */
    protected function sendViaAppliFlow(JobApplication $application, array $contacts, array $emailContent): int
    {
        $emailsSent = 0;
        $sendgrid = new SendGrid(env('SENDGRID_API_KEY'));

        // For testing, send to mattcieslak93@gmail.com instead of discovered contacts
        $email = new Mail();
        $email->setFrom("hq@appliflow.ai", $application->form_data_sent['personal']['full_name']);
        $email->setSubject($emailContent['subject']);
        $email->addTo("mattcieslak93@gmail.com");
        $email->addContent("text/plain", $emailContent['body']);

        // Attach resume if available
        if (!empty($emailContent['resume_path']) && file_exists(storage_path('app/' . $emailContent['resume_path']))) {
            $email->addAttachment(
                base64_encode(file_get_contents(storage_path('app/' . $emailContent['resume_path']))),
                "application/pdf",
                $emailContent['resume_name'],
                "attachment"
            );
        }

        try {
            $response = $sendgrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                $emailsSent = 1;
                Log::info("AppliFlow email sent successfully", [
                    'application_id' => $application->id,
                    'method' => 'appliflow',
                    'test_email' => 'mattcieslak93@gmail.com',
                    'discovered_contacts' => count($contacts)
                ]);
            } else {
                Log::error("Failed to send AppliFlow email", [
                    'application_id' => $application->id,
                    'status_code' => $response->statusCode(),
                    'response_body' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Exception sending AppliFlow email", [
                'application_id' => $application->id,
                'error' => $e->getMessage()
            ]);
        }

        return $emailsSent;
    }
    
    // NOTE: Playwright automation methods removed - replaced with email-based approach
    
    /**
     * Prepare user data for application forms
     */
    public function prepareApplicationData(User $user, Lead $lead, array $customResponses = []): array
    {
        // Get user's work experience and resume data
        $workExperience = $user->workExperience()->orderBy('start_date', 'desc')->get();
        $resume = $user->uploadedFiles()->where('file_type', 'resume')->latest()->first();
        
        // Get parsed resume data for more accurate information
        $parsedResume = $user->parsedResumes()->latest()->first();
        
        // Debug logging
        Log::info('PrepareApplicationData Debug', [
            'user_id' => $user->id,
            'has_parsed_resume' => !!$parsedResume,
            'parsed_resume_phone' => $parsedResume?->phone,
            'user_phone' => $user->phone,
            'parsed_resume_id' => $parsedResume?->id
        ]);
        
        // Split name if no separate first/last name fields, prefer parsed resume data
        $fullName = $parsedResume?->full_name ?? $user->name;
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        // Parse location into components
        $locationComponents = $this->parseLocationString($parsedResume?->location ?? $user->location ?? '');
        
        // If ZIP code is not found in location string, search for it in resume content
        if (empty($locationComponents['zip']) && $parsedResume && $parsedResume->raw_text) {
            if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $parsedResume->raw_text, $zipMatches)) {
                $locationComponents['zip'] = $zipMatches[1];
                Log::info('ZIP code extracted from resume content', ['zip' => $zipMatches[1]]);
            }
        }
        
        // Manual fallback for known ZIP codes (temporary fix)
        if (empty($locationComponents['zip']) && 
            $locationComponents['city'] === 'Williamstown' && 
            $locationComponents['state'] === 'NJ') {
            $locationComponents['zip'] = '08094';
            Log::info('Applied manual ZIP code fallback for Williamstown, NJ', ['zip' => '08094']);
        }
        
        $baseData = [
            'personal' => [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_name' => $fullName,
                'email' => $parsedResume?->email ?? $user->email,
                'phone' => $parsedResume?->phone ?? $user->phone ?? null,
                'linkedin_url' => $parsedResume?->linkedin_url ?? $user->linkedin_url ?? null,
                'portfolio_url' => $parsedResume?->portfolio_url ?? $user->portfolio_url ?? null,
                'address' => $locationComponents['address'] ?? '',
                'city' => $locationComponents['city'] ?? '',
                'state' => $locationComponents['state'] ?? '',
                'zip' => $locationComponents['zip'] ?? ''
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
                'current_location' => $parsedResume?->location ?? $user->location ?? 'Not specified',
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
     * Parse a location string into address components
     */
    private function parseLocationString(string $location): array
    {
        $components = [
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => ''
        ];

        if (empty($location)) {
            return $components;
        }

        // Log the location being parsed for debugging
        Log::info('Parsing location string', ['location' => $location]);

        // Common patterns:
        // "Williamstown NJ"
        // "New York, NY"
        // "123 Main St, Williamstown NJ 08094"
        // "Williamstown, NJ 08094"

        // Split by comma first
        $parts = array_map('trim', explode(',', $location));
        
        if (count($parts) == 1) {
            // No comma, likely "City State" or "City State Zip" format
            $singlePart = trim($parts[0]);
            
            // Look for zip code (5 digits or 5+4 format)
            if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $singlePart, $zipMatches)) {
                $components['zip'] = $zipMatches[1];
                $singlePart = trim(str_replace($zipMatches[1], '', $singlePart));
            }
            
            // Look for state (2-letter code at the end)
            if (preg_match('/\b([A-Z]{2})\s*$/', $singlePart, $stateMatches)) {
                $components['state'] = $stateMatches[1];
                $singlePart = trim(str_replace($stateMatches[1], '', $singlePart));
            }
            
            // Remaining is likely the city
            if (!empty($singlePart)) {
                $components['city'] = $singlePart;
            }
            
        } else {
            // Has comma(s), parse differently
            $lastPart = trim(array_pop($parts));
            
            // Check if last part has state and/or zip
            if (preg_match('/^([A-Z]{2})\s*(\d{5}(?:-\d{4})?)?\s*$/', $lastPart, $matches)) {
                $components['state'] = $matches[1];
                if (!empty($matches[2])) {
                    $components['zip'] = $matches[2];
                }
            } else if (preg_match('/\b(\d{5}(?:-\d{4})?)\b/', $lastPart, $zipMatches)) {
                $components['zip'] = $zipMatches[1];
                $remaining = trim(str_replace($zipMatches[1], '', $lastPart));
                if (preg_match('/\b([A-Z]{2})\b/', $remaining, $stateMatches)) {
                    $components['state'] = $stateMatches[1];
                }
            }
            
            // If we have more parts, assume the last remaining is city
            if (!empty($parts)) {
                $components['city'] = trim(array_pop($parts));
            }
            
            // Any remaining parts are likely address
            if (!empty($parts)) {
                $components['address'] = implode(', ', $parts);
            }
        }

        Log::info('Parsed location components', $components);
        
        return $components;
    }
    
    /**
     * Extract domain from URL
     */
    protected function extractDomainFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }
    
    // NOTE: getStrategyReason method removed - no longer needed with email-based approach
    
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
     * Discover form fields for email content generation (optional)
     * This can still be useful for gathering required information from job postings
     */
    protected function discoverFormFieldsForEmail(Lead $lead): array
    {
        // This method could optionally analyze the job posting to discover
        // what information employers typically ask for, which can then be
        // used to prompt users for missing data or enhance email content.
        // For now, we'll rely on the existing user_form_preferences system.

        $this->logStep(null, "Form field discovery for email content is optional in email-based approach");
        return [];
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
    
    // NOTE: User notification methods removed - email-based approach handles this automatically
}