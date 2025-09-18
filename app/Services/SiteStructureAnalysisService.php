<?php

namespace App\Services;

use App\Models\JobSiteStructure;
use App\Models\Lead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SiteStructureAnalysisService
{
    protected PlaywrightAutomationService $playwright;

    public function __construct(PlaywrightAutomationService $playwright)
    {
        $this->playwright = $playwright;
    }

    /**
     * Analyze a job site and create/update structure mapping
     */
    public function analyzeJobSite(Lead $lead): array
    {
        $domain = $this->extractDomain($lead->source_url);
        
        Log::info("Analyzing job site structure", [
            'domain' => $domain,
            'url' => $lead->source_url,
            'lead_id' => $lead->id
        ]);

        try {
            // First, try to detect the platform
            $platformInfo = $this->detectPlatform($lead->source_url);
            
            // Generate analysis script
            $analysisScript = $this->generateSiteAnalysisScript($lead->source_url);
            $analysisResult = $this->executeAnalysisScript($analysisScript, $lead->source_url);
            
            if (!$analysisResult['success']) {
                throw new \Exception($analysisResult['error'] ?? 'Site analysis failed');
            }
            
            // Create site structure record
            $siteStructure = $this->createSiteStructureRecord(
                $domain,
                $platformInfo,
                $analysisResult['analysis'],
                $lead->source_url
            );
            
            return [
                'success' => true,
                'site_structure' => $siteStructure,
                'analysis' => $analysisResult['analysis'],
                'message' => "Site structure analyzed and saved for {$domain}"
            ];
            
        } catch (\Exception $e) {
            Log::error("Site structure analysis failed", [
                'domain' => $domain,
                'url' => $lead->source_url,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'domain' => $domain
            ];
        }
    }

    /**
     * Try to detect what platform the job site uses
     */
    protected function detectPlatform(string $url): array
    {
        $domain = $this->extractDomain($url);
        
        // Known platform patterns
        $platformMap = [
            'ashbyhq.com' => 'Ashby',
            'workday.com' => 'Workday', 
            'greenhouse.io' => 'Greenhouse',
            'lever.co' => 'Lever',
            'bamboohr.com' => 'BambooHR',
            'successfactors.com' => 'SuccessFactors',
            'icims.com' => 'iCIMS',
            'jobvite.com' => 'Jobvite',
            'smartrecruiters.com' => 'SmartRecruiters'
        ];
        
        foreach ($platformMap as $pattern => $platform) {
            if (strpos($domain, $pattern) !== false) {
                return [
                    'platform' => $platform,
                    'confidence' => 'high',
                    'detected_by' => 'domain_pattern'
                ];
            }
        }
        
        // Try to detect by fetching the page and looking for indicators
        try {
            $response = Http::timeout(10)->get($url);
            $html = $response->body();
            
            // Look for platform indicators in HTML
            $indicators = [
                'Ashby' => ['ashbyhq', 'ashby-job-board'],
                'Workday' => ['workday', 'wd-template'],
                'Greenhouse' => ['greenhouse', 'grnhse'],
                'Lever' => ['lever.co', 'lever-framework'],
                'BambooHR' => ['bamboohr', 'bamboo-hr'],
                'Resumator' => ['resumator', 'theresumator', 'resumator-application-form', 'resumator-firstname']
            ];
            
            foreach ($indicators as $platform => $patterns) {
                foreach ($patterns as $pattern) {
                    if (stripos($html, $pattern) !== false) {
                        return [
                            'platform' => $platform,
                            'confidence' => 'medium',
                            'detected_by' => 'html_content'
                        ];
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::warning("Could not fetch page for platform detection", ['error' => $e->getMessage()]);
        }
        
        return [
            'platform' => 'Unknown',
            'confidence' => 'low',
            'detected_by' => 'fallback'
        ];
    }

    /**
     * Generate Playwright script to analyze site structure
     */
    protected function generateSiteAnalysisScript(string $url): string
    {
        return <<<'JAVASCRIPT'
const { chromium } = require('playwright');

async function analyzeSiteStructure() {
    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });
    
    const page = await context.newPage();
    
    try {
        console.log('Navigating to: {{TARGET_URL}}');
        await page.goto('{{TARGET_URL}}', { waitUntil: 'networkidle', timeout: 30000 });
        
        const analysis = {
            url: '{{TARGET_URL}}',
            title: await page.title(),
            forms: [],
            buttons: [],
            inputs: [],
            potential_apply_buttons: [],
            platform_indicators: [],
            has_captcha: false,
            navigation_flow: []
        };
        
        // Find all forms
        const forms = await page.locator('form').all();
        for (const form of forms) {
            try {
                const formInfo = {
                    action: await form.getAttribute('action') || '',
                    method: await form.getAttribute('method') || 'GET',
                    inputs: []
                };
                
                const inputs = await form.locator('input, select, textarea').all();
                for (const input of inputs) {
                    try {
                        formInfo.inputs.push({
                            type: await input.getAttribute('type') || 'text',
                            name: await input.getAttribute('name') || '',
                            placeholder: await input.getAttribute('placeholder') || '',
                            required: await input.getAttribute('required') !== null,
                            id: await input.getAttribute('id') || ''
                        });
                    } catch (e) {
                        // Skip problematic inputs
                    }
                }
                
                analysis.forms.push(formInfo);
            } catch (e) {
                // Skip problematic forms
            }
        }
        
        // Find potential apply buttons
        const applyButtonSelectors = [
            'button:has-text("Apply")',
            'a:has-text("Apply")', 
            'button:has-text("Apply for this job")',
            'input[value*="Apply"]',
            '[data-testid*="apply"]',
            '.apply-button',
            '#apply-button',
            'button:has-text("Submit Application")'
        ];
        
        for (const selector of applyButtonSelectors) {
            try {
                const elements = await page.locator(selector).all();
                for (const element of elements) {
                    if (await element.isVisible()) {
                        analysis.potential_apply_buttons.push({
                            selector: selector,
                            text: await element.textContent() || '',
                            href: await element.getAttribute('href') || null
                        });
                    }
                }
            } catch (e) {
                // Continue with other selectors
            }
        }
        
        // Check for CAPTCHA indicators
        const captchaSelectors = [
            '.g-recaptcha',
            '.h-captcha', 
            '#captcha',
            '.captcha',
            '.grecaptcha-badge'
        ];
        
        for (const selector of captchaSelectors) {
            try {
                const element = await page.locator(selector).first();
                if (await element.isVisible()) {
                    analysis.has_captcha = true;
                    break;
                }
            } catch (e) {
                // Continue checking
            }
        }
        
        // Look for platform indicators in page content
        const pageContent = await page.content();
        const platforms = ['ashby', 'workday', 'greenhouse', 'lever', 'bamboohr', 'jobvite', 'smartrecruiters'];
        
        for (const platform of platforms) {
            if (pageContent.toLowerCase().includes(platform)) {
                analysis.platform_indicators.push(platform);
            }
        }
        
        // Test clicking apply button to see navigation flow
        if (analysis.potential_apply_buttons.length > 0) {
            try {
                const firstApplyButton = analysis.potential_apply_buttons[0];
                
                // Take screenshot before clicking
                const beforeUrl = page.url();
                
                await page.locator(firstApplyButton.selector).first().click();
                await page.waitForLoadState('networkidle', { timeout: 10000 });
                
                const afterUrl = page.url();
                
                analysis.navigation_flow.push({
                    action: 'click_apply',
                    before_url: beforeUrl,
                    after_url: afterUrl,
                    changed_page: beforeUrl !== afterUrl
                });
                
                // If we're now on an application form, analyze that too
                if (beforeUrl !== afterUrl) {
                    const appFormInputs = await page.locator('input, select, textarea').all();
                    const applicationForm = { inputs: [] };
                    
                    for (const input of appFormInputs.slice(0, 20)) { // Limit to first 20
                        try {
                            applicationForm.inputs.push({
                                type: await input.getAttribute('type') || 'text',
                                name: await input.getAttribute('name') || '',
                                placeholder: await input.getAttribute('placeholder') || '',
                                required: await input.getAttribute('required') !== null,
                                id: await input.getAttribute('id') || ''
                            });
                        } catch (e) {
                            // Skip problematic inputs
                        }
                    }
                    
                    analysis.application_form = applicationForm;
                }
                
            } catch (e) {
                analysis.navigation_flow.push({
                    action: 'click_apply',
                    error: e.message
                });
            }
        }
        
        console.log(JSON.stringify({ success: true, analysis: analysis }));
        return { success: true, analysis: analysis };
        
    } catch (error) {
        console.error('Site analysis error:', error);
        console.log(JSON.stringify({ success: false, error: error.message }));
        return { success: false, error: error.message };
    } finally {
        await browser.close();
    }
}

// Run the analysis
analyzeSiteStructure();
JAVASCRIPT;
    }

    /**
     * Execute the site analysis script
     */
    protected function executeAnalysisScript(string $script, string $targetUrl = ''): array
    {
        // Replace the URL placeholder with the actual URL
        $script = str_replace('{{TARGET_URL}}', $targetUrl, $script);
        
        // Save script to temporary file  
        $tempScriptPath = tempnam(sys_get_temp_dir(), 'site_analysis_') . '.js';
        file_put_contents($tempScriptPath, $script);
        
        try {
            // Execute with timeout
            $command = "cd " . escapeshellarg(dirname($tempScriptPath)) . " && timeout 60 node " . escapeshellarg(basename($tempScriptPath));
            
            $output = '';
            $errorOutput = '';
            $startTime = time();
            
            exec($command . ' 2>&1', $outputLines, $returnCode);
            $output = implode("\n", $outputLines);
            
            // Try to parse JSON result
            $lines = explode("\n", $output);
            foreach (array_reverse($lines) as $line) {
                $line = trim($line);
                if (strpos($line, '{') === 0) {
                    $result = json_decode($line, true);
                    if ($result !== null) {
                        return $result;
                    }
                }
            }
            
            return [
                'success' => false,
                'error' => 'No valid analysis result found',
                'output' => $output
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            if (file_exists($tempScriptPath)) {
                unlink($tempScriptPath);
            }
        }
    }

    /**
     * Create site structure record from analysis
     */
    protected function createSiteStructureRecord(
        string $domain,
        array $platformInfo,
        array $analysis,
        string $originalUrl
    ): JobSiteStructure {
        
        // Determine automation strategy based on analysis
        $automationStrategy = $this->determineAutomationStrategy($analysis);
        
        // Build application flow from analysis
        $applicationFlow = $this->buildApplicationFlow($analysis);
        
        // Map form fields
        $formFields = $this->mapFormFields($analysis);
        
        // Create the record
        return JobSiteStructure::create([
            'domain' => $domain,
            'platform_name' => $platformInfo['platform'],
            'site_pattern' => $this->generateSitePattern($originalUrl),
            'application_flow' => $applicationFlow,
            'form_fields' => $formFields,
            'button_selectors' => $this->extractButtonSelectors($analysis),
            'field_mappings' => $this->generateFieldMappings($formFields),
            'success_indicators' => $this->generateSuccessIndicators($platformInfo['platform']),
            'error_selectors' => ['.error', '.field-error', ':has-text("required")', ':has-text("invalid")'],
            'has_captcha' => $analysis['has_captcha'] ?? false,
            'automation_strategy' => $automationStrategy,
            'notes' => "Auto-generated from site analysis. Platform: {$platformInfo['platform']} (confidence: {$platformInfo['confidence']})",
            'last_structure_update' => now(),
            'is_active' => true
        ]);
    }

    /**
     * Determine automation strategy based on analysis
     */
    protected function determineAutomationStrategy(array $analysis): string
    {
        // If has CAPTCHA, only semi-auto is safe
        if ($analysis['has_captcha'] ?? false) {
            return 'semi_auto';
        }
        
        // If we found apply buttons and forms, semi-auto is good
        if (!empty($analysis['potential_apply_buttons']) && !empty($analysis['forms'])) {
            return 'semi_auto';
        }
        
        // If navigation is complex or unclear, use iframe
        if (empty($analysis['potential_apply_buttons']) || empty($analysis['forms'])) {
            return 'iframe';
        }
        
        return 'semi_auto'; // Default safe option
    }

    /**
     * Build application flow from analysis
     */
    protected function buildApplicationFlow(array $analysis): array
    {
        $flow = ['steps' => []];
        
        $step = 1;
        
        // Step 1: Navigate to job posting
        $flow['steps'][] = [
            'step' => $step++,
            'action' => 'navigate',
            'description' => 'Navigate to job posting page'
        ];
        
        // Step 2: Click apply button if found
        if (!empty($analysis['potential_apply_buttons'])) {
            $flow['steps'][] = [
                'step' => $step++,
                'action' => 'click',
                'selector' => $analysis['potential_apply_buttons'][0]['selector'] ?? 'button:has-text("Apply")',
                'description' => 'Click apply button'
            ];
        }
        
        // Step 3: Fill form if found
        if (!empty($analysis['forms']) || !empty($analysis['application_form'])) {
            $flow['steps'][] = [
                'step' => $step++,
                'action' => 'fill_form',
                'description' => 'Fill application form'
            ];
        }
        
        // Step 4: Submit (semi-auto will stop here)
        $flow['steps'][] = [
            'step' => $step++,
            'action' => 'submit',
            'selector' => 'button:has-text("Submit")',
            'description' => 'Submit application'
        ];
        
        return $flow;
    }

    /**
     * Map form fields from analysis
     */
    protected function mapFormFields(array $analysis): array
    {
        $fieldMappings = [];
        
        // Get inputs from forms or application form
        $allInputs = [];
        
        foreach ($analysis['forms'] ?? [] as $form) {
            $allInputs = array_merge($allInputs, $form['inputs'] ?? []);
        }
        
        if (isset($analysis['application_form']['inputs'])) {
            $allInputs = array_merge($allInputs, $analysis['application_form']['inputs']);
        }
        
        // Map common field patterns
        foreach ($allInputs as $input) {
            $name = strtolower($input['name'] ?? '');
            $placeholder = strtolower($input['placeholder'] ?? '');
            $type = $input['type'] ?? 'text';
            
            // Name fields - improved detection for various platforms
            $isFirstName = strpos($name, 'first') !== false || strpos($placeholder, 'first') !== false ||
                          strpos($name, 'firstname') !== false || strpos($placeholder, 'firstname') !== false ||
                          strpos($name, 'given') !== false || strpos($placeholder, 'given') !== false;
            
            $isLastName = strpos($name, 'last') !== false || strpos($placeholder, 'last') !== false ||
                         strpos($name, 'lastname') !== false || strpos($placeholder, 'lastname') !== false ||
                         strpos($name, 'family') !== false || strpos($placeholder, 'family') !== false ||
                         strpos($name, 'surname') !== false || strpos($placeholder, 'surname') !== false;
            
            $isFullName = (strpos($name, 'name') !== false || strpos($placeholder, 'name') !== false) &&
                         !$isFirstName && !$isLastName;
            
            if ($isFirstName) {
                $fieldMappings['first_name'] = $this->createFieldMapping($input, 'personal.first_name');
            } elseif ($isLastName) {
                $fieldMappings['last_name'] = $this->createFieldMapping($input, 'personal.last_name');
            } elseif ($isFullName) {
                $fieldMappings['full_name'] = $this->createFieldMapping($input, 'personal.full_name');
            }
            
            // Email
            if ($type === 'email' || strpos($name, 'email') !== false || strpos($placeholder, 'email') !== false) {
                $fieldMappings['email'] = $this->createFieldMapping($input, 'personal.email');
            }
            
            // Phone
            if ($type === 'tel' || strpos($name, 'phone') !== false || strpos($placeholder, 'phone') !== false) {
                $fieldMappings['phone'] = $this->createFieldMapping($input, 'personal.phone');
            }
            
            // Resume
            if ($type === 'file') {
                $fieldMappings['resume'] = $this->createFieldMapping($input, 'resume.file_path');
            }
        }
        
        return $fieldMappings;
    }

    protected function createFieldMapping(array $input, string $dataSource): array
    {
        $selectors = [];
        
        if (!empty($input['name'])) {
            $selectors[] = "input[name=\"{$input['name']}\"]";
        }
        
        if (!empty($input['id'])) {
            $selectors[] = "#{$input['id']}";
        }
        
        if (!empty($input['placeholder'])) {
            $selectors[] = "input[placeholder*=\"{$input['placeholder']}\"]";
        }
        
        return [
            'selectors' => $selectors,
            'type' => $input['type'] ?? 'text',
            'required' => $input['required'] ?? false,
            'data_source' => $dataSource
        ];
    }

    protected function extractButtonSelectors(array $analysis): array
    {
        return [
            'apply_button' => [
                'selectors' => array_column($analysis['potential_apply_buttons'] ?? [], 'selector'),
                'page' => 'job_posting'
            ],
            'submit_button' => [
                'selectors' => ['button:has-text("Submit")', 'input[type="submit"]'],
                'page' => 'application_form'
            ]
        ];
    }

    protected function generateFieldMappings(array $formFields): array
    {
        $mappings = [];
        
        foreach ($formFields as $fieldName => $fieldInfo) {
            $mappings[$fieldName] = $fieldInfo['data_source'] ?? '';
        }
        
        return $mappings;
    }

    protected function generateSuccessIndicators(string $platform): array
    {
        return [
            'selectors' => [
                ':has-text("Thank you")',
                ':has-text("Application submitted")', 
                ':has-text("Successfully submitted")',
                '.confirmation-message',
                '.success'
            ],
            'url_patterns' => [
                'thank-you',
                'confirmation',
                'success',
                'applied'
            ]
        ];
    }

    protected function generateSitePattern(string $url): string
    {
        $parsed = parse_url($url);
        $domain = $parsed['host'] ?? '';
        
        return $domain . '/*';
    }

    protected function extractDomain(string $url): string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? '';
    }
}