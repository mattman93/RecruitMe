<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobSiteStructure;
use App\Models\Lead;
use App\Models\User;
use App\Models\UserFormPreference;
use App\Models\UserOAuthToken;
use App\Models\DiscoveredContact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use OpenAI\Laravel\Facades\OpenAI;
use SendGrid\Mail\Mail;
use SendGrid;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;

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
        // Use smart domain extraction that filters out job boards
        $domain = $this->extractCompanyDomain($lead);
        $companyName = $lead->company;

        // If we couldn't extract a valid company domain, log error and use company name as fallback
        if (!$domain) {
            Log::error("Could not extract company domain, cannot discover contact emails", [
                'lead_id' => $lead->id,
                'company' => $companyName,
                'source_url' => $lead->source_url
            ]);

            // Return empty array or ask OpenAI to find based on company name only
            return $this->discoverContactsByCompanyName($lead, $companyName);
        }

        Log::info("Discovering contact emails for company domain: {$domain}", [
            'company' => $companyName,
            'lead_id' => $lead->id
        ]);

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
                        'content' => "Given a company '{$companyName}' with domain '{$domain}', find all possible recruiting or HR-related emails (including likely patterns). Output as JSON array with 'email' and 'type' fields. Include common patterns like hr@, careers@, recruiting@, jobs@, talent@, etc. Do not include generic emails like info@ or support@. IMPORTANT: Only use the domain '{$domain}' - do NOT use job board domains like workable.com, greenhouse.io, lever.co, etc."
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
                    'company' => $companyName,
                    'contacts' => $contacts,
                    'api_cost_estimate' => '$0.003-0.006'
                ]);

                // Cache the discovered contacts
                DiscoveredContact::storeContacts($lead, $domain, $companyName, $contacts);

                // Also save contacts to the lead record
                $lead->update([
                    'discovered_contacts' => $contacts,
                    'discovered_contacts_at' => now()
                ]);

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

        // Also save fallback contacts to the lead record
        $lead->update([
            'discovered_contacts' => $fallbackContacts,
            'discovered_contacts_at' => now()
        ]);

        return $fallbackContacts;
    }

    /**
     * Discover contacts by company name only (when domain can't be extracted)
     */
    protected function discoverContactsByCompanyName(Lead $lead, string $companyName): array
    {
        Log::info("Attempting to discover domain for company: {$companyName}");

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Given a company name '{$companyName}', what is their likely primary domain/website? Return ONLY the domain (e.g., 'company.com') without http/https. If you're not confident, return null. Output as JSON with 'domain' field."
                    ]
                ],
                'max_tokens' => 100,
                'temperature' => 0.1
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if (!empty($result['domain'])) {
                $discoveredDomain = $result['domain'];
                Log::info("OpenAI discovered domain from company name", [
                    'company' => $companyName,
                    'domain' => $discoveredDomain
                ]);

                // Now discover emails for this domain
                $contacts = [
                    ['email' => "careers@{$discoveredDomain}", 'type' => 'careers'],
                    ['email' => "hr@{$discoveredDomain}", 'type' => 'hr'],
                    ['email' => "recruiting@{$discoveredDomain}", 'type' => 'recruiting']
                ];

                DiscoveredContact::storeContacts($lead, $discoveredDomain, $companyName, $contacts);

                // Also save to lead record
                $lead->update([
                    'discovered_contacts' => $contacts,
                    'discovered_contacts_at' => now()
                ]);

                return $contacts;
            }
        } catch (\Exception $e) {
            Log::error("Failed to discover domain from company name: " . $e->getMessage());
        }

        // Ultimate fallback - return empty array
        Log::warning("Could not discover any contact emails", [
            'lead_id' => $lead->id,
            'company' => $companyName
        ]);

        return [];
    }

    /**
     * Generate application email content based on job and user data
     */
    protected function generateApplicationEmail(JobApplication $application): array
    {
        $user = $application->user;
        $lead = $application->lead;
        $formData = $application->form_data_sent;

        // Discover form fields for this job (if not already discovered)
        $discoveredFields = $this->getOrDiscoverFormFields($lead);

        // Get user's resume file
        $resume = $user->uploadedFiles()->where('file_type', 'resume')->latest()->first();

        // Generate subject line
        $subject = "Application for {$lead->title} - {$formData['personal']['full_name']}";

        // Generate personalized value proposition
        $valueProposition = $this->generateValueProposition($lead, $formData, $application->user);

        // Generate email body using form data and user preferences
        $contactEmails = $this->discoverContactEmails($lead);
        $body = $this->generateEmailBody($lead, $formData, $application->custom_responses, $contactEmails, $valueProposition, $discoveredFields);

        return [
            'subject' => $subject,
            'body' => $body,
            'resume_path' => $resume?->file_path,
            'resume_name' => $resume?->original_name ?? 'resume.pdf',
            'discovered_fields' => $discoveredFields
        ];
    }

    /**
     * Get discovered fields for a lead (from cache or discover new)
     */
    protected function getOrDiscoverFormFields(Lead $lead): array
    {
        // Check if we already have discovered fields for this lead
        if ($lead->discovered_fields && $lead->fields_discovered_at) {
            // Use cached fields if discovered within last 7 days
            if ($lead->fields_discovered_at->diffInDays(now()) <= 7) {
                Log::info("Using cached discovered fields", [
                    'lead_id' => $lead->id,
                    'fields_count' => count($lead->discovered_fields),
                    'discovered_at' => $lead->fields_discovered_at
                ]);
                return $lead->discovered_fields;
            }
        }

        // Discover new fields
        return $this->discoverFormFieldsForEmail($lead);
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
    protected function generateEmailBody(Lead $lead, array $formData, array $customResponses, array $discoveredContacts = [], string $valueProposition = '', array $discoveredFields = []): string
    {
        $personal = $formData['personal'];
        $experience = $formData['experience'];

        $body = "";

        // Note: Discovered contacts are used for routing but not shown in email content

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

        // Add discovered field responses
        $discoveredResponses = $this->getDiscoveredFieldResponses($discoveredFields, $formData, $customResponses);
        if (!empty($discoveredResponses)) {
            $body .= "Application Requirements:\n\n";
            foreach ($discoveredResponses as $question => $answer) {
                $body .= "• {$question}: {$answer}\n";
            }
            $body .= "\n";
        }

        // Add custom responses if any were collected
        if (!empty($customResponses)) {
            $body .= "Additional information:\n\n";
            foreach ($customResponses as $question => $answer) {
                $body .= "• {$question}: {$answer}\n";
            }
            $body .= "\n";
        }

        // Note: Discovered fields are used internally but not shown in email content

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

        // Add debug information if TEST_EMAIL_SHOW_DEBUG is enabled
        if (env('TEST_EMAIL_SHOW_DEBUG', false)) {
            $body .= "\n\n" . str_repeat("-", 80) . "\n";
            $body .= "DEBUG INFORMATION (Only visible in TEST_EMAIL_SHOW_DEBUG mode)\n";
            $body .= str_repeat("-", 80) . "\n\n";

            // Show discovered contacts
            if (!empty($discoveredContacts)) {
                $body .= "DISCOVERED CONTACT EMAILS:\n";
                foreach ($discoveredContacts as $contact) {
                    $email = $contact['email'] ?? 'N/A';
                    $type = $contact['type'] ?? 'unknown';
                    $body .= "  • {$email} ({$type})\n";
                }
                $body .= "\n";
            }

            // Show discovered fields
            if (!empty($discoveredFields)) {
                $body .= "DISCOVERED FORM FIELDS:\n";
                foreach ($discoveredFields as $field) {
                    $fieldName = $field['field_name'] ?? 'unknown';
                    $question = $field['question'] ?? 'N/A';
                    $fieldType = $field['field_type'] ?? 'unknown';
                    $body .= "  • Field: {$fieldName}\n";
                    $body .= "    Question: {$question}\n";
                    $body .= "    Type: {$fieldType}\n\n";
                }
            }

            // Show lead details
            $body .= "LEAD DETAILS:\n";
            $body .= "  • ID: {$lead->id}\n";
            $body .= "  • Company: {$lead->company}\n";
            $body .= "  • Source URL: {$lead->source_url}\n";
            if (!empty($lead->company_website)) {
                $body .= "  • Company Website: {$lead->company_website}\n";
            }
            if (!empty($lead->company_linkedin_url)) {
                $body .= "  • Company LinkedIn: {$lead->company_linkedin_url}\n";
            }
            $body .= "\n";

            $body .= str_repeat("-", 80) . "\n";
            $body .= "END DEBUG INFORMATION\n";
            $body .= str_repeat("-", 80) . "\n";
        }

        return $body;
    }

    /**
     * Get responses for discovered fields by checking user preferences and generating smart defaults
     */
    protected function getDiscoveredFieldResponses(array $discoveredFields, array $formData, array $customResponses): array
    {
        $responses = [];
        $userId = auth()->id();

        foreach ($discoveredFields as $field) {
            $fieldName = $field['field_name'] ?? '';
            $question = $field['question'] ?? '';
            $fieldType = $field['field_type'] ?? 'text';

            // Skip if we already have this in custom responses
            if (isset($customResponses[$question])) {
                continue;
            }

            // Try to get existing user preference
            $preference = null;
            if ($userId) {
                $preference = UserFormPreference::where('user_id', $userId)
                    ->where('field_identifier', $fieldName)
                    ->first();
            }

            if ($preference) {
                // Use existing preference
                $responses[$question] = $this->formatPreferenceResponse($preference);
            } else {
                // Check if we should use smart defaults or prompt user
                if (env('AUTO_GENERATE_FIELD_RESPONSES', true)) {
                    // Generate smart default based on field type and name
                    $smartDefault = $this->generateSmartDefault($field, $formData);
                    if ($smartDefault) {
                        $responses[$question] = $smartDefault;
                    }
                } else {
                    // Mark this field as needing user input (for future enhancement)
                    // For now, skip unanswered fields
                    Log::info("Skipping unanswered field - user preference needed", [
                        'field_name' => $fieldName,
                        'question' => $question,
                        'field_type' => $fieldType
                    ]);
                }
            }
        }

        return array_filter($responses); // Remove empty responses
    }

    /**
     * Get fields that need user input (for UI prompting)
     */
    public function getFieldsNeedingUserInput(Lead $lead, int $userId): array
    {
        $discoveredFields = $this->getOrDiscoverFormFields($lead);
        $fieldsNeedingInput = [];

        foreach ($discoveredFields as $field) {
            $fieldName = $field['field_name'] ?? '';

            // Check if user has existing preference
            $preference = UserFormPreference::where('user_id', $userId)
                ->where('field_identifier', $fieldName)
                ->first();

            if (!$preference) {
                $fieldsNeedingInput[] = [
                    'field_name' => $fieldName,
                    'question' => $field['question'] ?? '',
                    'field_type' => $field['field_type'] ?? 'text',
                    'options' => $field['options'] ?? null,
                    'suggested_answer' => $this->generateSmartDefault($field, [])
                ];
            }
        }

        return $fieldsNeedingInput;
    }


    /**
     * Generate smart defaults for unknown fields based on common patterns
     */
    protected function generateSmartDefault(array $field, array $formData): ?string
    {
        $fieldName = strtolower($field['field_name'] ?? '');
        $question = strtolower($field['question'] ?? '');
        $fieldType = $field['field_type'] ?? 'text';

        // Work authorization patterns
        if (str_contains($fieldName, 'work_auth') || str_contains($question, 'authorized to work')) {
            return "Yes, I am authorized to work in the United States";
        }

        // Visa sponsorship patterns
        if (str_contains($fieldName, 'visa') || str_contains($question, 'sponsorship')) {
            return "I do not require visa sponsorship";
        }

        // Security clearance patterns
        if (str_contains($fieldName, 'clearance') || str_contains($question, 'security clearance')) {
            return "I do not currently hold a security clearance but am eligible to obtain one";
        }

        // Salary expectations
        if (str_contains($fieldName, 'salary') || str_contains($question, 'compensation')) {
            return "I am open to discussing compensation based on the role's responsibilities and market rates";
        }

        // Start date / availability
        if (str_contains($fieldName, 'start') || str_contains($question, 'availability')) {
            return "I am available to start within 2-4 weeks notice";
        }

        // Relocation
        if (str_contains($fieldName, 'relocate') || str_contains($question, 'willing to relocate')) {
            return "I am open to relocation for the right opportunity";
        }

        // Remote work
        if (str_contains($fieldName, 'remote') || str_contains($question, 'remote work')) {
            return "I am flexible with remote, hybrid, or on-site work arrangements";
        }

        // Years of experience
        if (str_contains($fieldName, 'experience') || str_contains($question, 'years of experience')) {
            $experience = $formData['experience'] ?? [];
            $totalYears = count($experience); // Simple calculation
            return "I have {$totalYears}+ years of relevant experience";
        }

        // Boolean fields default to positive
        if ($fieldType === 'boolean') {
            return "Yes";
        }

        // For other text fields, return null to skip
        return null;
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
        // Handle both new JSON format and legacy format
        $responseData = $preference->response_data;
        if (is_string($responseData) && str_starts_with($responseData, '{')) {
            $decoded = json_decode($responseData, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decoded['value'])) {
                $responseData = $decoded['value'];
            }
        }

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
     * Send via user's Gmail account using Gmail API
     */
    protected function sendViaUserEmail(JobApplication $application, array $contacts, array $emailContent): int
    {
        $user = $application->user;
        $emailsSent = 0;

        try {
            // Get user's Google OAuth token using DB facade (temporary workaround)
            $tokenData = \DB::table('user_oauth_tokens')
                ->where('user_id', $user->id)
                ->where('provider', 'google')
                ->first();

            if (!$tokenData) {
                Log::error("No Google OAuth token found for user", [
                    'user_id' => $user->id,
                    'user_email' => $user->email
                ]);
                return 0;
            }

            // Convert to object for easier access
            $oauthToken = (object) [
                'access_token' => $tokenData->access_token,
                'refresh_token' => $tokenData->refresh_token,
                'expires_at' => $tokenData->expires_at ? \Carbon\Carbon::parse($tokenData->expires_at) : null,
                'scopes' => $tokenData->scopes ? json_decode($tokenData->scopes, true) : []
            ];

            // Initialize Google Client
            $client = new Google_Client();
            $client->setClientId(env('GOOGLE_CLIENT_ID'));
            $client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
            $client->setAccessToken($oauthToken->access_token);

            // Check if token is expired and refresh if needed
            if ($client->isAccessTokenExpired()) {
                Log::info("Google OAuth token is expired, attempting refresh", [
                    'user_id' => $user->id,
                    'expired_at' => $oauthToken->expires_at
                ]);

                if (!$oauthToken->refresh_token) {
                    Log::error("No refresh token available, user needs to re-authenticate", [
                        'user_id' => $user->id
                    ]);
                    return 0;
                }

                try {
                    // Refresh the access token
                    $newToken = $client->fetchAccessTokenWithRefreshToken($oauthToken->refresh_token);

                    if (isset($newToken['error'])) {
                        Log::error("Failed to refresh Google OAuth token", [
                            'user_id' => $user->id,
                            'error' => $newToken['error'],
                            'error_description' => $newToken['error_description'] ?? null
                        ]);
                        return 0;
                    }

                    // Update the token in database
                    \DB::table('user_oauth_tokens')
                        ->where('user_id', $user->id)
                        ->where('provider', 'google')
                        ->update([
                            'access_token' => $newToken['access_token'],
                            'expires_at' => isset($newToken['expires_in'])
                                ? now()->addSeconds($newToken['expires_in'])
                                : null,
                            'updated_at' => now()
                        ]);

                    // Update local object
                    $oauthToken->access_token = $newToken['access_token'];
                    $client->setAccessToken($newToken['access_token']);

                    Log::info("Successfully refreshed Google OAuth token", [
                        'user_id' => $user->id,
                        'new_expires_at' => isset($newToken['expires_in'])
                            ? now()->addSeconds($newToken['expires_in'])->toDateTimeString()
                            : 'unknown'
                    ]);

                } catch (\Exception $e) {
                    Log::error("Exception while refreshing Google OAuth token", [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                    return 0;
                }
            }

            // Check if token has Gmail send permission
            $hasGmailScope = in_array('https://www.googleapis.com/auth/gmail.send', $oauthToken->scopes);
            if (!$hasGmailScope) {
                Log::error("User OAuth token doesn't have Gmail send permission", [
                    'user_id' => $user->id,
                    'scopes' => $oauthToken->scopes
                ]);
                return 0;
            }

            // Initialize Gmail service
            $gmail = new Google_Service_Gmail($client);

            Log::info("Sending via user's Gmail account", [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'contacts' => count($contacts)
            ]);

            // Determine recipients based on TEST_EMAIL_MODE
            $recipients = [];
            if (env('TEST_EMAIL_MODE', true)) {
                // Test mode: send only to test email
                $recipients = [['email' => 'mattcieslak93@gmail.com', 'type' => 'Test Email']];
                Log::info("Gmail API test mode enabled - sending to test email only", [
                    'user_id' => $user->id,
                    'test_email' => 'mattcieslak93@gmail.com',
                    'discovered_contacts' => count($contacts)
                ]);
            } else {
                // Production mode: send to discovered contacts
                $recipients = $contacts;
                Log::info("Gmail API production mode - sending to discovered contacts", [
                    'user_id' => $user->id,
                    'recipients' => count($contacts)
                ]);
            }

            // Send email to each recipient
            foreach ($recipients as $contact) {
                try {
                    $rawMessage = $this->createGmailMessage(
                        $user->email,
                        $contact['email'],
                        $emailContent['subject'],
                        $emailContent['body'],
                        $emailContent['resume_path'] ?? null,
                        $emailContent['resume_name'] ?? 'resume.pdf'
                    );

                    $message = new Google_Service_Gmail_Message();
                    $message->setRaw($rawMessage);

                    $gmail->users_messages->send('me', $message);
                    $emailsSent++;

                    Log::info("Gmail API email sent successfully", [
                        'user_id' => $user->id,
                        'recipient' => $contact['email'],
                        'contact_type' => $contact['type']
                    ]);

                } catch (\Exception $e) {
                    Log::error("Failed to send Gmail API email to contact", [
                        'user_id' => $user->id,
                        'recipient' => $contact['email'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if ($emailsSent > 0) {
                Log::info("Gmail API emails sent successfully", [
                    'user_id' => $user->id,
                    'emails_sent' => $emailsSent,
                    'test_email_mode' => env('TEST_EMAIL_MODE', true),
                    'recipients' => env('TEST_EMAIL_MODE', true) ? ['mattcieslak93@gmail.com'] : array_column($contacts, 'email'),
                    'total_contacts' => count($contacts)
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Gmail API sending failed", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $emailsSent;
    }

    /**
     * Create a properly formatted Gmail message with optional attachment
     */
    protected function createGmailMessage(string $from, string $to, string $subject, string $body, ?string $resumePath = null, string $resumeName = 'resume.pdf'): string
    {
        $boundary = uniqid(rand(), true);

        // Start with headers
        $message = "From: {$from}\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";

        // Add text body
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";

        // Add resume attachment if provided
        if ($resumePath && file_exists(storage_path('app/' . $resumePath))) {
            $fileContent = file_get_contents(storage_path('app/' . $resumePath));
            $encodedContent = base64_encode($fileContent);

            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/pdf; name=\"{$resumeName}\"\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$resumeName}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split($encodedContent, 76, "\r\n");
        }

        $message .= "--{$boundary}--";

        // Base64url encode the entire message
        return $this->base64UrlEncode($message);
    }

    /**
     * Base64url encoding (Gmail API requirement)
     */
    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Send via AppliFlow (SendGrid) - current implementation
     */
    protected function sendViaAppliFlow(JobApplication $application, array $contacts, array $emailContent): int
    {
        $emailsSent = 0;
        $sendgrid = new SendGrid(env('SENDGRID_API_KEY'));

        $email = new Mail();
        $email->setFrom("hq@appliflow.ai", $application->form_data_sent['personal']['full_name']);
        $email->setSubject($emailContent['subject']);

        // Check if in test email mode
        if (env('TEST_EMAIL_MODE', true)) {
            // Send to test email only
            $email->addTo("mattcieslak93@gmail.com");
        } else {
            // Send to discovered contacts or fallback to test email
            if (!empty($contacts)) {
                foreach ($contacts as $contact) {
                    $email->addTo($contact['email']);
                }
            } else {
                // Fallback to test email if no contacts discovered
                $email->addTo("mattcieslak93@gmail.com");
                Log::warning("No discovered contacts found, sending to fallback email", [
                    'application_id' => $application->id,
                    'lead_id' => $application->lead_id
                ]);
            }
        }
        $email->addContent("text/plain", $emailContent['body']);

        // Attach resume if available
        if (!empty($emailContent['resume_path']) && file_exists(storage_path('app/' . $emailContent['resume_path']))) {
            $resumeContent = file_get_contents(storage_path('app/' . $emailContent['resume_path']));
            $email->addAttachment(
                base64_encode($resumeContent),
                "application/pdf",
                $emailContent['resume_name'],
                "attachment"
            );

            Log::info("Resume attachment added", [
                'application_id' => $application->id,
                'resume_name' => $emailContent['resume_name'],
                'resume_size' => strlen($resumeContent)
            ]);
        } else {
            Log::warning("Resume file not found or path empty", [
                'application_id' => $application->id,
                'resume_path' => $emailContent['resume_path'] ?? 'null',
                'expected_full_path' => !empty($emailContent['resume_path']) ? storage_path('app/' . $emailContent['resume_path']) : 'null'
            ]);
        }

        try {
            $response = $sendgrid->send($email);

            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                $emailsSent = 1;
                Log::info("AppliFlow email sent successfully", [
                    'application_id' => $application->id,
                    'method' => 'appliflow',
                    'test_email_mode' => env('TEST_EMAIL_MODE', true),
                    'recipients' => env('TEST_EMAIL_MODE', true) ? ['mattcieslak93@gmail.com'] : array_column($contacts, 'email'),
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

        // Add discovered fields and their responses
        $discoveredFields = $this->getOrDiscoverFormFields($lead);
        if (!empty($discoveredFields)) {
            $discoveredResponses = $this->getDiscoveredFieldResponses($discoveredFields, $baseData, $customResponses);
            $baseData['discovered_fields'] = $discoveredFields;
            $baseData['discovered_responses'] = $discoveredResponses;
        }

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
        // If URL doesn't have a scheme, add one for parse_url to work
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';

        // Remove www. prefix if present
        $host = preg_replace('/^www\./', '', $host);

        return $host;
    }

    /**
     * Known job board domains to filter out
     */
    protected function getJobBoardDomains(): array
    {
        return [
            'apply.workable.com',
            'workable.com',
            'greenhouse.io',
            'lever.co',
            'apply.lever.co',
            'jobs.lever.co',
            'myworkdayjobs.com',
            'workday.com',
            'smartrecruiters.com',
            'jobs.smartrecruiters.com',
            'icims.com',
            'breezy.hr',
            'recruiting.ultipro.com',
            'paycomonline.com',
            'taleo.net',
            'oraclecloud.com',
            'successfactors.com',
            'sap.com',
            'applytojob.com',
            'bamboohr.com',
            'jobvite.com',
            'recruiterbox.com',
            'indeed.com',
            'linkedin.com',
            'monster.com',
            'ziprecruiter.com',
            'glassdoor.com',
            'dice.com',
            'careerbuilder.com',
            'simplyhired.com',
            'jobs.com',
            'snagajob.com',
        ];
    }

    /**
     * Check if a domain is a job board (including subdomains)
     */
    protected function isJobBoardDomain(string $domain): bool
    {
        $jobBoardDomains = $this->getJobBoardDomains();

        // Direct match
        if (in_array($domain, $jobBoardDomains)) {
            return true;
        }

        // Check if domain ends with any job board domain (catches subdomains)
        // e.g., fmr.wd1.myworkdayjobs.com ends with myworkdayjobs.com
        foreach ($jobBoardDomains as $jobBoard) {
            if (str_ends_with($domain, '.' . $jobBoard) || str_ends_with($domain, $jobBoard)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract company domain from Lead data, filtering out job board domains
     */
    protected function extractCompanyDomain(Lead $lead): ?string
    {
        // Priority 1: Check company_website field
        if (!empty($lead->company_website)) {
            $domain = $this->extractDomainFromUrl($lead->company_website);
            if ($domain && !$this->isJobBoardDomain($domain)) {
                Log::info("Using company website domain", ['domain' => $domain]);
                return $domain;
            }
        }

        // Priority 2: Check company_linkedin_url for domain hints
        if (!empty($lead->company_linkedin_url)) {
            // Try to extract domain from LinkedIn URL pattern
            // Sometimes LinkedIn URLs contain company slugs we can use
            $domain = $this->extractDomainFromUrl($lead->company_linkedin_url);
            if ($domain === 'linkedin.com' || $domain === 'www.linkedin.com') {
                // Extract company slug from LinkedIn and try to guess domain
                if (preg_match('/linkedin\.com\/company\/([^\/]+)/', $lead->company_linkedin_url, $matches)) {
                    $companySlug = $matches[1];
                    // This is a guess - may not always work
                    $guessedDomain = str_replace('-', '', $companySlug) . '.com';
                    Log::info("Guessed domain from LinkedIn slug", [
                        'slug' => $companySlug,
                        'guessed_domain' => $guessedDomain
                    ]);
                    // Mark this as low confidence
                }
            }
        }

        // Priority 3: Check source_url, but filter out job boards
        if (!empty($lead->source_url)) {
            $domain = $this->extractDomainFromUrl($lead->source_url);
            if ($domain && !$this->isJobBoardDomain($domain)) {
                Log::info("Using source URL domain (not a job board)", ['domain' => $domain]);
                return $domain;
            } else {
                Log::info("Source URL is a job board, skipping", ['domain' => $domain]);
            }
        }

        // Priority 4: Try to extract from apply_url
        if (!empty($lead->apply_url)) {
            $domain = $this->extractDomainFromUrl($lead->apply_url);
            if ($domain && !$this->isJobBoardDomain($domain)) {
                Log::info("Using apply URL domain (not a job board)", ['domain' => $domain]);
                return $domain;
            }
        }

        Log::warning("Could not extract valid company domain from Lead data", [
            'lead_id' => $lead->id,
            'company' => $lead->company
        ]);

        return null;
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
     * Discover form fields for email content generation
     * Analyzes job posting to find common application requirements
     */
    protected function discoverFormFieldsForEmail(Lead $lead): array
    {
        try {
            // Prepare job context for field discovery
            $jobContext = "Company: " . ($lead->company ?? 'Unknown');
            $jobContext .= "\nPosition: " . $lead->job_title;
            $jobContext .= "\nJob URL: " . $lead->source_url;

            if ($lead->description) {
                $jobContext .= "\nJob Description: " . substr($lead->description, 0, 2000);
            }

            Log::info("Discovering form fields for lead", [
                'lead_id' => $lead->id,
                'company' => $lead->company,
                'title' => $lead->job_title
            ]);

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Analyze this job posting and identify specific application questions/requirements that employers commonly ask beyond basic contact info and resume. Return as JSON array with objects containing 'field_name', 'question', 'field_type' (text/select/boolean), and 'options' (for select fields). Focus on: work authorization, security clearance, certifications, salary expectations, availability, specific skills, etc.\n\n{$jobContext}"
                    ]
                ],
                'max_tokens' => 800,
                'temperature' => 0.1
            ]);

            $content = $response->choices[0]->message->content;
            $discoveredFields = json_decode($content, true);

            if (is_array($discoveredFields) && !empty($discoveredFields)) {
                Log::info("Discovered form fields", [
                    'lead_id' => $lead->id,
                    'fields_count' => count($discoveredFields),
                    'fields' => $discoveredFields,
                    'api_cost_estimate' => '$0.008-0.016'
                ]);

                // Store discovered fields for this lead
                $this->storeDiscoveredFields($lead, $discoveredFields);

                return $discoveredFields;
            }

        } catch (\Exception $e) {
            Log::warning("Form field discovery failed: " . $e->getMessage(), [
                'lead_id' => $lead->id
            ]);
        }

        return [];
    }

    /**
     * Store discovered fields for future use
     */
    protected function storeDiscoveredFields(Lead $lead, array $fields): void
    {
        try {
            // Update the lead with discovered fields
            $lead->update([
                'discovered_fields' => $fields,
                'fields_discovered_at' => now()
            ]);

            Log::info("Stored discovered fields for lead", [
                'lead_id' => $lead->id,
                'fields_count' => count($fields)
            ]);

        } catch (\Exception $e) {
            Log::warning("Failed to store discovered fields: " . $e->getMessage(), [
                'lead_id' => $lead->id
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
    
    // NOTE: User notification methods removed - email-based approach handles this automatically
}