<?php

namespace App\Services;

use App\Models\JobApplication;
use App\Models\JobSiteStructure;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PlaywrightAutomationService
{
    protected string $playwrightPath;
    protected string $screenshotPath;
    
    public function __construct()
    {
        // These paths would be configured based on your environment
        $this->playwrightPath = '/usr/local/bin/playwright'; // Or wherever Playwright is installed
        $this->screenshotPath = storage_path('app/application_screenshots');
        
        // Ensure screenshot directory exists
        if (!file_exists($this->screenshotPath)) {
            mkdir($this->screenshotPath, 0755, true);
        }
    }
    
    /**
     * Fill and submit application form completely
     */
    public function fillAndSubmitApplication(
        Lead $lead, 
        ?JobSiteStructure $siteStructure, 
        array $formData, 
        JobApplication $application
    ): array {
        // Check if we're in test mode
        if (env('TEST_MODE', false)) {
            return $this->simulateApplication($lead, $siteStructure, $formData, $application, 'full');
        }
        
        $startTime = microtime(true);
        
        try {
            // Generate Playwright script for this specific site
            $script = $this->generatePlaywrightScript([
                'url' => $lead->source_url,
                'site_structure' => $siteStructure,
                'form_data' => $formData,
                'action' => 'fill_and_submit',
                'application_id' => $application->id
            ]);
            
            $result = $this->executePlaywrightScript($script);
            $duration = microtime(true) - $startTime;
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'final_url' => $result['final_url'] ?? null,
                    'confirmation_number' => $result['confirmation_number'] ?? null,
                    'confirmation_message' => $result['confirmation_message'] ?? null,
                    'duration' => round($duration),
                    'screenshots' => $result['screenshots'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown automation error',
                    'duration' => round($duration),
                    'screenshots' => $result['screenshots'] ?? []
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration' => round(microtime(true) - $startTime)
            ];
        }
    }
    
    /**
     * Fill form only, don't submit (for semi-automation)
     */
    public function fillFormOnly(
        Lead $lead, 
        ?JobSiteStructure $siteStructure, 
        array $formData, 
        JobApplication $application
    ): array {
        // Check if we're in test mode
        if (env('TEST_MODE', false)) {
            return $this->simulateApplication($lead, $siteStructure, $formData, $application, 'fill_only');
        }
        
        try {
            $script = $this->generatePlaywrightScript([
                'url' => $lead->source_url,
                'site_structure' => $siteStructure,
                'form_data' => $formData,
                'action' => 'fill_only',
                'application_id' => $application->id
            ]);
            
            $result = $this->executePlaywrightScript($script);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'final_url' => $result['final_url'] ?? null,
                    'session_data' => [
                        'browser_session_id' => $result['session_id'] ?? null,
                        'filled_fields' => $result['filled_fields'] ?? []
                    ],
                    'screenshots' => $result['screenshots'] ?? []
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to fill form',
                    'screenshots' => $result['screenshots'] ?? []
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate Playwright JavaScript/Python script
     */
    protected function generatePlaywrightScript(array $config): string
    {
        $siteStructure = $config['site_structure'];
        $formData = $config['form_data'];
        $action = $config['action']; // 'fill_and_submit' or 'fill_only'
        $applicationId = $config['application_id'];
        
        // Generate JavaScript script for Playwright
        $script = $this->getPlaywrightScriptTemplate();
        
        // Replace placeholders with actual values
        $replacements = [
            '{{TARGET_URL}}' => $config['url'],
            '{{ACTION_TYPE}}' => $action,
            '{{APPLICATION_ID}}' => $applicationId,
            '{{FORM_DATA}}' => json_encode($formData),
            '{{SITE_STRUCTURE}}' => json_encode($siteStructure?->toArray() ?? []),
            '{{SCREENSHOT_PATH}}' => $this->screenshotPath,
            '{{USER_AGENT}}' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        
        foreach ($replacements as $placeholder => $value) {
            $script = str_replace($placeholder, $value, $script);
        }
        
        return $script;
    }
    
    /**
     * Get the Playwright script template
     */
    protected function getPlaywrightScriptTemplate(): string
    {
        return <<<'JAVASCRIPT'
const { chromium } = require('playwright');
const fs = require('fs');

async function automateApplication() {
    const browser = await chromium.launch({ 
        headless: false, // Set to true for production
        slowMo: 500 // Slow down actions to appear more human
    });
    
    const context = await browser.newContext({
        userAgent: '{{USER_AGENT}}',
        viewport: { width: 1366, height: 768 }
    });
    
    const page = await context.newPage();
    
    try {
        console.log('Navigating to: {{TARGET_URL}}');
        await page.goto('{{TARGET_URL}}', { waitUntil: 'networkidle' });
        
        // Take initial screenshot
        await page.screenshot({ 
            path: '{{SCREENSHOT_PATH}}/app_{{APPLICATION_ID}}_initial.png' 
        });
        
        const formData = {{FORM_DATA}};
        const siteStructure = {{SITE_STRUCTURE}};
        const actionType = '{{ACTION_TYPE}}';
        
        // Look for and click "Apply" button to get to application form
        const applyButtonSelectors = [
            'button:has-text("Apply")',
            'a:has-text("Apply")',
            'button:has-text("Apply for this job")',
            'input[value*="Apply"]',
            '[data-testid*="apply"]',
            '.apply-button',
            '#apply-button'
        ];
        
        let applyButtonFound = false;
        for (const selector of applyButtonSelectors) {
            try {
                const element = await page.locator(selector).first();
                if (await element.isVisible({ timeout: 2000 })) {
                    console.log(`Found apply button: ${selector}`);
                    await element.click();
                    await page.waitForLoadState('networkidle');
                    applyButtonFound = true;
                    break;
                }
            } catch (e) {
                // Continue to next selector
            }
        }
        
        if (!applyButtonFound) {
            console.log('No apply button found, assuming we are already on application form');
        }
        
        // Take screenshot after navigation
        await page.screenshot({ 
            path: '{{SCREENSHOT_PATH}}/app_{{APPLICATION_ID}}_form.png' 
        });
        
        // Fill form fields
        const filledFields = [];
        
        // Name fields
        await fillFieldIfExists(page, ['input[name="firstName"]', '#firstName', 'input[placeholder*="First"]'], formData.personal.first_name, filledFields);
        await fillFieldIfExists(page, ['input[name="lastName"]', '#lastName', 'input[placeholder*="Last"]'], formData.personal.last_name, filledFields);
        await fillFieldIfExists(page, ['input[name="name"]', '#name', 'input[placeholder*="Full name"]'], formData.personal.full_name, filledFields);
        
        // Email
        await fillFieldIfExists(page, ['input[name="email"]', '#email', 'input[type="email"]'], formData.personal.email, filledFields);
        
        // Phone
        if (formData.personal.phone) {
            await fillFieldIfExists(page, ['input[name="phone"]', '#phone', 'input[type="tel"]'], formData.personal.phone, filledFields);
        }
        
        // Resume upload
        if (formData.resume.file_path) {
            const fileInputSelectors = ['input[type="file"]', 'input[name*="resume"]', 'input[accept*="pdf"]'];
            for (const selector of fileInputSelectors) {
                try {
                    const fileInput = await page.locator(selector).first();
                    if (await fileInput.isVisible({ timeout: 2000 })) {
                        await fileInput.setInputFiles(formData.resume.file_path);
                        filledFields.push(`File uploaded: ${selector}`);
                        break;
                    }
                } catch (e) {
                    // Continue
                }
            }
        }
        
        // Work authorization questions
        await handleYesNoQuestion(page, ['Do you have', 'authorized to work'], true, filledFields);
        await handleYesNoQuestion(page, ['visa sponsorship', 'require sponsorship'], false, filledFields);
        
        // Location/Remote work preferences
        const locationSelectors = ['input[name*="location"]', 'select[name*="location"]'];
        if (formData.location.current_location) {
            await fillFieldIfExists(page, locationSelectors, formData.location.current_location, filledFields);
        }
        
        // Take screenshot after filling
        await page.screenshot({ 
            path: '{{SCREENSHOT_PATH}}/app_{{APPLICATION_ID}}_filled.png' 
        });
        
        let result = {
            success: true,
            final_url: page.url(),
            filled_fields: filledFields,
            screenshots: [
                `app_{{APPLICATION_ID}}_initial.png`,
                `app_{{APPLICATION_ID}}_form.png`,
                `app_{{APPLICATION_ID}}_filled.png`
            ]
        };
        
        // If we're doing full automation, try to submit
        if (actionType === 'fill_and_submit') {
            const submitButtonSelectors = [
                'button:has-text("Submit")',
                'input[type="submit"]',
                'button[type="submit"]',
                'button:has-text("Submit Application")',
                'button:has-text("Apply")',
                '[data-testid*="submit"]'
            ];
            
            let submitButtonFound = false;
            for (const selector of submitButtonSelectors) {
                try {
                    const element = await page.locator(selector).first();
                    if (await element.isVisible({ timeout: 2000 })) {
                        console.log(`Found submit button: ${selector}`);
                        await element.click();
                        
                        // Wait for submission to complete
                        await page.waitForLoadState('networkidle');
                        
                        // Take final screenshot
                        await page.screenshot({ 
                            path: '{{SCREENSHOT_PATH}}/app_{{APPLICATION_ID}}_submitted.png' 
                        });
                        result.screenshots.push(`app_{{APPLICATION_ID}}_submitted.png`);
                        
                        submitButtonFound = true;
                        break;
                    }
                } catch (e) {
                    // Continue
                }
            }
            
            if (!submitButtonFound) {
                result.success = false;
                result.error = 'Could not find submit button';
            } else {
                // Try to extract confirmation message
                try {
                    const confirmationSelectors = [
                        ':has-text("Thank you")',
                        ':has-text("Application submitted")',
                        ':has-text("Successfully")',
                        '.confirmation',
                        '.success'
                    ];
                    
                    for (const selector of confirmationSelectors) {
                        try {
                            const element = await page.locator(selector).first();
                            if (await element.isVisible({ timeout: 5000 })) {
                                result.confirmation_message = await element.textContent();
                                break;
                            }
                        } catch (e) {
                            // Continue
                        }
                    }
                } catch (e) {
                    console.log('Could not extract confirmation message');
                }
            }
        }
        
        console.log(JSON.stringify(result));
        return result;
        
    } catch (error) {
        console.error('Automation error:', error);
        await page.screenshot({ 
            path: '{{SCREENSHOT_PATH}}/app_{{APPLICATION_ID}}_error.png' 
        });
        
        return {
            success: false,
            error: error.message,
            screenshots: [`app_{{APPLICATION_ID}}_error.png`]
        };
    } finally {
        await browser.close();
    }
}

async function fillFieldIfExists(page, selectors, value, filledFields) {
    if (!value) return;
    
    for (const selector of selectors) {
        try {
            const element = await page.locator(selector).first();
            if (await element.isVisible({ timeout: 2000 })) {
                await element.fill(value);
                filledFields.push(`${selector}: ${value}`);
                return;
            }
        } catch (e) {
            // Continue to next selector
        }
    }
}

async function handleYesNoQuestion(page, textPhrases, answer, filledFields) {
    for (const phrase of textPhrases) {
        try {
            // Look for radio buttons or checkboxes near text containing the phrase
            const container = await page.locator(`:has-text("${phrase}")`).first();
            if (await container.isVisible({ timeout: 2000 })) {
                const yesSelector = answer ? 'input[value="yes"], input[value="Yes"], input[value="true"]' : 'input[value="no"], input[value="No"], input[value="false"]';
                const yesInput = container.locator(yesSelector).first();
                
                if (await yesInput.isVisible({ timeout: 1000 })) {
                    await yesInput.check();
                    filledFields.push(`${phrase}: ${answer ? 'Yes' : 'No'}`);
                    return;
                }
            }
        } catch (e) {
            // Continue
        }
    }
}

// Run the automation
automateApplication();
JAVASCRIPT;
    }
    
    /**
     * Simulate application processing for TEST_MODE
     */
    protected function simulateApplication(
        Lead $lead, 
        ?JobSiteStructure $siteStructure, 
        array $formData, 
        JobApplication $application,
        string $mode = 'full'
    ): array {
        // Simulate processing time
        sleep(rand(2, 5));
        
        // Simulate random success/failure (80% success rate)
        $success = rand(1, 100) <= 80;
        
        if ($success) {
            return [
                'success' => true,
                'final_url' => $lead->source_url . '/confirmation',
                'confirmation_number' => 'TEST-' . strtoupper(substr(md5($application->id . time()), 0, 8)),
                'confirmation_message' => 'Thank you for your application! We will review it and get back to you soon.',
                'duration' => rand(15, 45),
                'screenshots' => [
                    "test_app_{$application->id}_initial.png",
                    "test_app_{$application->id}_form.png",
                    "test_app_{$application->id}_filled.png",
                    $mode === 'full' ? "test_app_{$application->id}_submitted.png" : null
                ],
                'session_data' => $mode === 'fill_only' ? [
                    'browser_session_id' => 'test-session-' . $application->id,
                    'filled_fields' => [
                        'First Name: ' . $formData['personal']['first_name'],
                        'Last Name: ' . $formData['personal']['last_name'],
                        'Email: ' . $formData['personal']['email'],
                        'Resume uploaded successfully'
                    ]
                ] : null
            ];
        } else {
            // Simulate various failure scenarios
            $errors = [
                'Could not find application form on the page',
                'Failed to upload resume file',
                'Application form validation failed',
                'Site returned an error during submission',
                'Could not find submit button'
            ];
            
            return [
                'success' => false,
                'error' => $errors[array_rand($errors)],
                'duration' => rand(10, 30),
                'screenshots' => [
                    "test_app_{$application->id}_error.png"
                ]
            ];
        }
    }

    /**
     * Execute the Playwright script
     */
    protected function executePlaywrightScript(string $script): array
    {
        // Save script to temporary file
        $tempScriptPath = tempnam(sys_get_temp_dir(), 'playwright_') . '.js';
        file_put_contents($tempScriptPath, $script);
        
        try {
            // Execute the script with Node.js
            $command = "cd " . escapeshellarg(dirname($tempScriptPath)) . " && node " . escapeshellarg(basename($tempScriptPath));
            
            $process = Process::start($command);
            $output = '';
            $errorOutput = '';
            
            // Set timeout for the process
            $timeout = 120; // 2 minutes
            $startTime = time();
            
            while ($process->running() && (time() - $startTime) < $timeout) {
                $output .= $process->output();
                $errorOutput .= $process->errorOutput();
                usleep(100000); // 0.1 second
            }
            
            if ($process->running()) {
                $process->kill();
                throw new \Exception('Playwright script timed out after ' . $timeout . ' seconds');
            }
            
            $output .= $process->output();
            $errorOutput .= $process->errorOutput();
            
            // Try to parse JSON result from output
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
            
            // If no JSON found, return error
            return [
                'success' => false,
                'error' => 'No valid result returned from Playwright script',
                'output' => $output,
                'error_output' => $errorOutput
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        } finally {
            // Clean up temp file
            if (file_exists($tempScriptPath)) {
                unlink($tempScriptPath);
            }
        }
    }
}