<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ApprovedDomain;
use DOMDocument;
use DOMXPath;

class JobProxyController extends Controller
{
    /**
     * Proxy a job site through our server to bypass X-Frame-Options
     */
    public function proxyJobSite(Request $request)
    {
        $url = $request->query('url');
        
        Log::info('Job proxy request received', ['url' => $url]);
        
        if (!$url) {
            return response()->json(['error' => 'URL parameter required'], 400);
        }
        
        // Special handling for test learning URL to avoid self-referential proxy
        if (str_contains($url, '/test-learning')) {
            $testController = new \App\Http\Controllers\TestController();
            $testResponse = $testController->showTestForm($request);
            $html = $testResponse->getContent();
            return response($this->processHtml($html, $url))->header('Content-Type', 'text/html');
        }
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL format'], 400);
        }
        
        // Security: Check if domain is approved (dynamic system)
        $parsedUrl = parse_url($url);
        $domain = $parsedUrl['host'] ?? '';
        
        // Always allow localhost for development
        $localhostDomains = ['localhost', '127.0.0.1'];
        $isLocalhost = in_array($domain, $localhostDomains);
        
        if (!$isLocalhost) {
            $isApproved = false;
            
            try {
                // Check if domain is approved in our dynamic system
                $isApproved = ApprovedDomain::isApproved($domain);
                
                if (!$isApproved) {
                    // Try to auto-approve if domain has leads
                    $domainsFromLeads = ApprovedDomain::getDomainsFromLeads();
                    
                    if (array_key_exists($domain, $domainsFromLeads)) {
                        // Auto-approve domain since it has leads
                        ApprovedDomain::approve(
                            $domain, 
                            'lead', 
                            "Auto-approved from {$domainsFromLeads[$domain]} job leads"
                        );
                        $isApproved = true;
                        Log::info('Auto-approved domain from leads', [
                            'domain' => $domain,
                            'leads_count' => $domainsFromLeads[$domain]
                        ]);
                    }
                }
                
                // Record domain usage
                if ($isApproved) {
                    $approvedDomain = ApprovedDomain::where('domain', $domain)->first();
                    if ($approvedDomain) {
                        $approvedDomain->recordUsage();
                    }
                }
                
            } catch (\Exception $e) {
                // Fallback: If approved_domains table doesn't exist, check if domain has leads directly
                Log::info('ApprovedDomain table not available, using fallback domain check', [
                    'domain' => $domain,
                    'error' => $e->getMessage()
                ]);
                
                // Check if this domain has leads in the database
                $leadCount = \DB::table('leads')
                    ->whereRaw('SUBSTRING_INDEX(SUBSTRING_INDEX(source_url, "://", -1), "/", 1) = ?', [$domain])
                    ->where('is_active', true)
                    ->count();
                
                if ($leadCount > 0) {
                    $isApproved = true;
                    Log::info('Domain approved via fallback - has leads', [
                        'domain' => $domain,
                        'leads_count' => $leadCount
                    ]);
                } else {
                    // Final fallback: Allow common job board domains
                    $commonJobSites = [
                        'greenhouse.io', 'lever.co', 'workday.com', 'icims.com', 
                        'bamboohr.com', 'smartrecruiters.com', 'jobvite.com',
                        'theresumator.com', 'resumator.com', 'applytojob.com',
                        'breezy.hr', 'ashbyhq.com', 'workforcenow.adp.com'
                    ];
                    
                    foreach ($commonJobSites as $jobSite) {
                        if (str_ends_with($domain, $jobSite)) {
                            $isApproved = true;
                            Log::info('Domain approved via common job sites fallback', [
                                'domain' => $domain,
                                'matched_pattern' => $jobSite
                            ]);
                            break;
                        }
                    }
                }
            }
            
            if (!$isApproved) {
                Log::warning('Proxy request denied for unapproved domain', [
                    'domain' => $domain,
                    'url' => $url
                ]);
                return response()->json(['error' => 'Domain not approved for proxy access'], 403);
            }
        }
        
        try {
            // Fetch the external page with proper headers
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Upgrade-Insecure-Requests' => '1',
            ])->timeout(30)->get($url);
            
            if (!$response->successful()) {
                Log::warning('Job proxy failed to fetch URL', [
                    'url' => $url,
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return response()->json(['error' => 'Failed to fetch job page'], $response->status());
            }
            
            $html = $response->body();
            
            // Process and modify the HTML
            $modifiedHtml = $this->processHtml($html, $url);
            
            return response($modifiedHtml)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('X-Frame-Options', 'SAMEORIGIN'); // Allow our own iframe
                
        } catch (\Exception $e) {
            Log::error('Job proxy error', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['error' => 'Proxy request failed'], 500);
        }
    }
    
    /**
     * Process and modify HTML content
     */
    private function processHtml(string $html, string $baseUrl): string
    {
        // Create DOMDocument to parse HTML
        $dom = new DOMDocument();
        
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        
        // Load HTML with UTF-8 encoding
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Restore error handling
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Convert relative URLs to absolute
        $this->convertRelativeUrls($dom, $xpath, $baseUrl);
        
        // Modify problematic script content
        $this->modifyScriptContent($dom, $xpath, $baseUrl);
        
        // Remove problematic elements
        $this->removeProblematicElements($dom, $xpath);
        
        // Inject our custom JavaScript for auto-fill
        $this->injectCustomScripts($dom, $baseUrl);
        
        // Get modified HTML
        $modifiedHtml = $dom->saveHTML();
        
        // Additional string-based modifications
        $modifiedHtml = $this->postProcessHtml($modifiedHtml, $baseUrl);
        
        return $modifiedHtml;
    }
    
    /**
     * Convert relative URLs to absolute URLs
     */
    private function convertRelativeUrls(DOMDocument $dom, DOMXPath $xpath, string $baseUrl): void
    {
        $parsedBase = parse_url($baseUrl);
        $baseScheme = $parsedBase['scheme'] ?? 'https';
        $baseHost = $parsedBase['host'] ?? '';
        $basePath = $parsedBase['path'] ?? '';
        
        // Convert relative src attributes
        $elements = $xpath->query('//img[@src]|//script[@src]|//link[@href]');
        foreach ($elements as $element) {
            $attr = $element->hasAttribute('src') ? 'src' : 'href';
            $url = $element->getAttribute($attr);
            
            if (!empty($url) && !filter_var($url, FILTER_VALIDATE_URL)) {
                // Convert relative to absolute
                if (str_starts_with($url, '//')) {
                    $url = $baseScheme . ':' . $url;
                } elseif (str_starts_with($url, '/')) {
                    $url = $baseScheme . '://' . $baseHost . $url;
                } else {
                    $url = $baseScheme . '://' . $baseHost . dirname($basePath) . '/' . $url;
                }
                
                $element->setAttribute($attr, $url);
            }
        }
    }
    
    /**
     * Modify problematic script content by fetching and cleaning external scripts
     */
    private function modifyScriptContent(DOMDocument $dom, DOMXPath $xpath, string $baseUrl): void
    {
        $scriptElements = $xpath->query('//script[@src]');
        
        foreach ($scriptElements as $scriptElement) {
            $src = $scriptElement->getAttribute('src');
            
            // Check if this is a problematic script
            if (strpos($src, 'submit-resume') !== false) {
                try {
                    Log::info('Modifying problematic script', ['src' => $src]);
                    
                    // Fetch the script content
                    $response = Http::timeout(10)->get($src);
                    
                    if ($response->successful()) {
                        $scriptContent = $response->body();
                        
                        // Remove or replace the problematic line
                        $scriptContent = preg_replace(
                            '/document\.domain\s*=\s*get_base_domain\(\)\s*;?\s*/i',
                            '// document.domain assignment removed to prevent iframe security error',
                            $scriptContent
                        );
                        
                        // Also remove any other domain assignments
                        $scriptContent = preg_replace(
                            '/document\.domain\s*=.*?;/i',
                            '// document.domain assignment removed',
                            $scriptContent
                        );
                        
                        // Replace the external script with inline script containing modified content
                        $scriptElement->removeAttribute('src');
                        $scriptElement->appendChild($dom->createTextNode($scriptContent));
                        
                        Log::info('Successfully modified script content');
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to modify script content', ['error' => $e->getMessage()]);
                    // Fall back to removing the script entirely
                    $scriptElement->parentNode->removeChild($scriptElement);
                }
            }
        }
    }
    
    /**
     * Remove elements that might cause issues
     */
    private function removeProblematicElements(DOMDocument $dom, DOMXPath $xpath): void
    {
        // Remove elements that might interfere
        $selectorsToRemove = [
            '//meta[@http-equiv="X-Frame-Options"]',
            '//meta[@http-equiv="Content-Security-Policy"]',
            '//script[contains(text(), "framebusting")]',
            '//script[contains(text(), "top.location")]',
            '//script[contains(@src, "submit-resume")]',
            '//script[contains(text(), "document.domain")]',
        ];
        
        foreach ($selectorsToRemove as $selector) {
            $elements = $xpath->query($selector);
            foreach ($elements as $element) {
                $element->parentNode->removeChild($element);
            }
        }
    }
    
    /**
     * Inject custom JavaScript for auto-fill functionality
     */
    private function injectCustomScripts(DOMDocument $dom, string $baseUrl = ''): void
    {
        $script = $dom->createElement('script');
        $script->setAttribute('type', 'text/javascript');
        
        // Extract domain info for navigation interception
        $originalDomain = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        $originalScheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
        $appUrl = config('app.url');
        
        // Create enhanced auto-fill JavaScript with learning system
        $jsCode = '
(function() {
    "use strict";
    
    // Prevent conflicts with existing site JavaScript
    if (window.APPLIFLOW_LOADED) {
        console.log("AppliFlow already loaded, skipping...");
        return;
    }
    window.APPLIFLOW_LOADED = true;
    
    console.log("AppliFlow auto-fill script loaded on URL:", window.location.href);
    
    // Learning System Configuration
    const LEARNING_CONFIG = {
        apiBase: "' . config('app.url') . '/api",
        userId: null,
        sessionKey: "appliflow_form_data_" + window.location.host,
        enabled: true
    };
    
    // Get user ID from parent window or session
    function getCurrentUserId() {
        try {
            if (window.parent && window.parent.APPLIFLOW_USER_ID) {
                console.log("📋 Got user ID from parent window:", window.parent.APPLIFLOW_USER_ID);
                return window.parent.APPLIFLOW_USER_ID;
            }
            // Try to get from sessionStorage
            const stored = sessionStorage.getItem("appliflow_user_id");
            if (stored) {
                console.log("📋 Got user ID from session storage:", stored);
                return parseInt(stored);
            }
            
            // For test environment, try to fetch current user via API
            if (window.location.href.includes("test-learning")) {
                console.log("📋 Test environment detected, using default user ID");
                // Store a test user ID in session for future use
                sessionStorage.setItem("appliflow_user_id", "1");
                return 1; // Default test user ID
            }
            
            console.warn("📋 No user ID found");
            return null;
        } catch (e) {
            console.warn("Could not get user ID:", e);
            return null;
        }
    }
    
    // Fetch current user ID via API for test environment
    function fetchCurrentUser() {
        fetch("/api/auth/check", {
            credentials: "include",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            }
        }).then(response => response.json())
        .then(data => {
            if (data.authenticated && data.user_id) {
                LEARNING_CONFIG.userId = data.user_id;
                sessionStorage.setItem("appliflow_user_id", data.user_id.toString());
                console.log("✅ User ID fetched:", data.user_id);
                // Update debug display if present
                const userIdElement = document.getElementById("user-id");
                if (userIdElement) {
                    userIdElement.textContent = data.user_id;
                }
            }
        }).catch(e => {
            console.warn("Could not fetch user ID:", e);
        });
    }
    
    // Store form data in session storage to persist across page reloads
    function storeFormData(data) {
        try {
            const existing = JSON.parse(sessionStorage.getItem(LEARNING_CONFIG.sessionKey) || "{}");
            const merged = Object.assign(existing, data);
            sessionStorage.setItem(LEARNING_CONFIG.sessionKey, JSON.stringify(merged));
            console.log("📦 Stored form data in session:", merged);
        } catch (e) {
            console.warn("Failed to store form data:", e);
        }
    }
    
    // Retrieve stored form data
    function getStoredFormData() {
        try {
            const data = JSON.parse(sessionStorage.getItem(LEARNING_CONFIG.sessionKey) || "{}");
            console.log("📦 Retrieved stored form data:", data);
            return data;
        } catch (e) {
            console.warn("Failed to retrieve stored form data:", e);
            return {};
        }
    }
    
    // Get CSRF token for API requests (disabled for learning endpoint)
    async function getCSRFToken() {
        return null; // CSRF not needed for learning endpoint
    }

    // Send form preference to learning API
    async function storeFormPreference(questionText, fieldType, responseData) {
        const userId = getCurrentUserId();
        if (!userId || !LEARNING_CONFIG.enabled) {
            console.log("⚠️ Learning disabled - no user ID or learning disabled");
            return;
        }
        
        try {
            console.log("🧠 Storing form preference:", { questionText, fieldType, responseData });
            
            // Get CSRF token for the request
            const csrfToken = await getCSRFToken();
            const headers = {
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            };
            
            if (csrfToken) {
                headers["X-CSRF-TOKEN"] = csrfToken;
            }
            
            const response = await fetch(LEARNING_CONFIG.apiBase + "/form-preferences", {
                method: "POST",
                headers: headers,
                credentials: "include",
                body: JSON.stringify({
                    question_text: questionText,
                    field_type: fieldType,
                    response_data: responseData
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                console.log("✅ Form preference stored successfully:", result);
            } else {
                console.warn("❌ Failed to store form preference:", response.status, await response.text());
            }
        } catch (e) {
            console.error("❌ Error storing form preference:", e);
        }
    }
    
    // Auto-fill system to populate stored preferences
    async function initializeAutoFill() {
        console.log("🔄 Initializing auto-fill system...");
        
        const userId = getCurrentUserId();
        if (!userId) {
            console.log("⚠️ Auto-fill disabled - no user ID");
            return;
        }
        
        try {
            // Fetch stored form preferences for this user
            const headers = {
                "X-Requested-With": "XMLHttpRequest"
            };
            
            const response = await fetch(LEARNING_CONFIG.apiBase + "/form-preferences", {
                method: "GET",
                headers: headers,
                credentials: "include"
            });
            
            if (response.ok) {
                const data = await response.json();
                console.log("📋 Retrieved stored preferences:", data);
                
                if (data.preferences && data.preferences.length > 0) {
                    autoFillFormFields(data.preferences);
                } else {
                    console.log("📝 No stored preferences found");
                }
            } else {
                console.warn("⚠️ Failed to fetch preferences:", response.status);
            }
        } catch (e) {
            console.error("❌ Error fetching preferences:", e);
        }
    }
    
    // Auto-fill form fields based on stored preferences
    function autoFillFormFields(preferences) {
        console.log("🎯 Auto-filling form fields with", preferences.length, "stored preferences");
        
        let filledCount = 0;
        
        preferences.forEach(function(preference) {
            const questionText = preference.question_text;
            const fieldType = preference.field_type;
            const responseData = preference.response_data;
            
            console.log("🔍 Looking for field:", questionText, "Type:", fieldType, "Value:", responseData);
            
            // Find matching form fields
            const matchingFields = findFieldsByQuestion(questionText, fieldType);
            
            matchingFields.forEach(function(field) {
                if (fillFormField(field, fieldType, responseData)) {
                    filledCount++;
                    // Mark as auto-filled
                    markFieldAsFilled(field);
                    console.log("✅ Auto-filled field:", field.name || field.id, "with value:", responseData);
                }
            });
        });
        
        console.log("🎉 Auto-fill completed:", filledCount, "fields populated");
    }
    
    // Find form fields that match a stored preference
    function findFieldsByQuestion(questionText, fieldType) {
        const matches = [];
        const forms = document.querySelectorAll("form");
        
        forms.forEach(function(form) {
            const elements = form.querySelectorAll("input, select, textarea");
            
            elements.forEach(function(element) {
                // Skip if already filled by auto-fill
                if (element.dataset.appliflowFilled) return;
                
                const elementQuestionText = getQuestionText(element);
                const elementFieldType = getFieldType(element);
                
                // Match by question text and field type
                if (elementQuestionText && 
                    elementQuestionText.toLowerCase().includes(questionText.toLowerCase()) && 
                    elementFieldType === fieldType) {
                    matches.push(element);
                }
            });
        });
        
        return matches;
    }
    
    // Fill a specific form field with stored value
    function fillFormField(field, fieldType, value) {
        try {
            switch (fieldType) {
                case "radio":
                    if (field.value === value) {
                        field.checked = true;
                        field.dispatchEvent(new Event("change", { bubbles: true }));
                        return true;
                    }
                    break;
                    
                case "checkbox":
                    field.checked = value === true || value === "true" || value === 1 || value === "1";
                    field.dispatchEvent(new Event("change", { bubbles: true }));
                    return true;
                    
                case "select":
                case "yes_no":
                    // Find matching option by text or value
                    for (let i = 0; i < field.options.length; i++) {
                        const option = field.options[i];
                        if (option.text === value || option.value === value) {
                            field.selectedIndex = i;
                            field.dispatchEvent(new Event("change", { bubbles: true }));
                            return true;
                        }
                    }
                    break;
                    
                case "text":
                default:
                    field.value = value;
                    field.dispatchEvent(new Event("input", { bubbles: true }));
                    field.dispatchEvent(new Event("change", { bubbles: true }));
                    return true;
            }
        } catch (e) {
            console.warn("⚠️ Failed to fill field:", field.name || field.id, e);
        }
        
        return false;
    }
    
    // Helper function: Get question text from form field context
    function getQuestionText(element) {
        const selectors = [
            `label[for="${element.id}"]`,
            element.closest("label"),
            element.parentElement?.querySelector("label")
        ];
        
        for (const selector of selectors) {
            let text = null;
            
            if (typeof selector === "string" && selector) {
                const labelEl = document.querySelector(selector);
                text = labelEl?.textContent?.trim();
            } else if (selector && selector.textContent) {
                text = selector.textContent.trim();
            }
            
            if (text && text.length > 3) {
                return text;
            }
        }
        
        // Fallback: look for text near the field
        const parent = element.parentElement;
        if (parent) {
            const allText = parent.textContent.trim();
            if (allText && allText.length > 3 && allText.length < 200) {
                return allText;
            }
        }
        
        return element.name || element.id || "Unknown question";
    }
    
    // Helper function: Determine field type for learning system
    function getFieldType(element) {
        if (element.type === "checkbox" || element.type === "radio") {
            return element.type;
        }
        if (element.tagName === "SELECT") {
            return "select";
        }
        if (element.type === "textarea" || element.tagName === "TEXTAREA") {
            return "text";
        }
        
        // Check if this is a yes/no question based on options
        if (element.tagName === "SELECT") {
            const options = Array.from(element.options).map(opt => opt.text.toLowerCase().trim());
            if (options.some(opt => opt.includes("yes")) && options.some(opt => opt.includes("no"))) {
                return "yes_no";
            }
        }
        
        return "text";
    }
    
    // Helper function: Get response data based on field type
    function getResponseData(element) {
        if (element.type === "checkbox") {
            return element.checked;
        }
        if (element.type === "radio") {
            // For radio buttons, return the value of the checked radio in the same group
            const radioGroup = document.querySelectorAll(`input[name="${element.name}"]`);
            for (const radio of radioGroup) {
                if (radio.checked) {
                    return radio.value;
                }
            }
            return null;
        }
        if (element.tagName === "SELECT") {
            const selected = element.options[element.selectedIndex];
            return selected ? selected.text || selected.value : null;
        }
        return element.value;
    }

    // Monitor form field changes to capture learning data
    function initializeLearningMonitor() {
        console.log("🧠 Initializing learning monitor...");
        
        // Monitor all form interactions
        document.addEventListener("change", function(event) {
            const element = event.target;
            console.log("📝 Change event detected:", element.tagName, element.type, element.name || element.id);
            
            if (!element || !element.form) return;
            
            // Skip if this is a pre-filled field
            if (element.dataset.appliflowFilled) return;
            
            const questionText = getQuestionText(element);
            const fieldType = getFieldType(element);
            const responseData = getResponseData(element);
            
            console.log("🔍 Field change detected:", {
                element: element.tagName + (element.type ? "[" + element.type + "]" : ""),
                name: element.name || element.id,
                question: questionText,
                type: fieldType,
                response: responseData,
                responseType: typeof responseData
            });
            
            // For radio buttons, always try to learn if any radio in the group is selected
            if (element.type === "radio" && responseData === null) {
                console.log("📻 Radio button detected but no value selected yet");
                return;
            }
            
            if (questionText && responseData !== null && responseData !== "") {
                console.log("🎯 Learning opportunity detected:", {
                    element: element.tagName + (element.type ? "[" + element.type + "]" : ""),
                    question: questionText,
                    type: fieldType,
                    response: responseData
                });
                
                // Store immediately to capture before any page reload
                storeFormPreference(questionText, fieldType, responseData);
                
                // Also store in session for page reload recovery
                const fieldKey = element.name || element.id || "unknown_field";
                storeFormData({
                    [fieldKey]: {
                        question: questionText,
                        type: fieldType,
                        response: responseData,
                        timestamp: Date.now()
                    }
                });
            }
        });
        
        // Monitor before page unload to capture any pending data
        window.addEventListener("beforeunload", function() {
            console.log("📤 Page unloading - storing any pending form data");
            storeCurrentFormState();
        });
        
        console.log("✅ Learning monitor initialized");
    }
    
    // Get question text from form field context
    function getQuestionText(element) {
        const selectors = [
            // Try label associated with field
            `label[for="${element.id}"]`,
            // Try parent label
            element.closest("label"),
            // Try preceding label
            element.parentElement?.querySelector("label"),
            // Try data attributes
            element.dataset.question,
            element.title,
            element.placeholder
        ];
        
        for (const selector of selectors) {
            let text = null;
            
            if (typeof selector === "string" && selector) {
                const labelEl = document.querySelector(selector);
                text = labelEl?.textContent?.trim();
            } else if (selector && selector.textContent) {
                text = selector.textContent.trim();
            } else if (typeof selector === "string") {
                text = selector;
            }
            
            if (text && text.length > 3) {
                return text;
            }
        }
        
        // Fallback: look for text near the field
        const parent = element.parentElement;
        if (parent) {
            const allText = parent.textContent.trim();
            if (allText && allText.length > 3 && allText.length < 200) {
                return allText;
            }
        }
        
        return element.name || element.id || "Unknown question";
    }
    
    // Determine field type for learning system
    function getFieldType(element) {
        if (element.type === "checkbox" || element.type === "radio") {
            return element.type;
        }
        if (element.tagName === "SELECT") {
            return "select";
        }
        if (element.type === "textarea" || element.tagName === "TEXTAREA") {
            return "text";
        }
        
        // Check if this is a yes/no question based on options
        if (element.tagName === "SELECT") {
            const options = Array.from(element.options).map(opt => opt.text.toLowerCase().trim());
            if (options.some(opt => opt.includes("yes")) && options.some(opt => opt.includes("no"))) {
                return "yes_no";
            }
        }
        
        return "text";
    }
    
    // Get response data based on field type
    function getResponseData(element) {
        if (element.type === "checkbox") {
            return element.checked;
        }
        if (element.type === "radio") {
            // For radio buttons, return the value of the checked radio in the same group
            const radioGroup = document.querySelectorAll("input[name=\\"" + element.name + "\\"]");
            for (const radio of radioGroup) {
                if (radio.checked) {
                    return radio.value;
                }
            }
            return null;
        }
        if (element.tagName === "SELECT") {
            const selected = element.options[element.selectedIndex];
            return selected ? selected.text || selected.value : null;
        }
        return element.value;
    }
    
    // Store current form state before navigation
    function storeCurrentFormState() {
        const formData = {};
        const forms = document.querySelectorAll("form");
        
        forms.forEach(form => {
            const elements = form.querySelectorAll("input, select, textarea");
            elements.forEach(element => {
                if (element.name && element.value && !element.dataset.appliflowFilled) {
                    const questionText = getQuestionText(element);
                    const fieldType = getFieldType(element);
                    const responseData = getResponseData(element);
                    
                    if (questionText && responseData !== null && responseData !== "") {
                        formData[element.name] = {
                            question: questionText,
                            type: fieldType,
                            response: responseData,
                            timestamp: Date.now()
                        };
                    }
                }
            });
        });
        
        if (Object.keys(formData).length > 0) {
            storeFormData(formData);
        }
    }
    
    // Restore form data after page reload
    function restoreFormData() {
        const storedData = getStoredFormData();
        if (Object.keys(storedData).length === 0) return;
        
        console.log("🔄 Restoring form data after page reload...");
        
        Object.entries(storedData).forEach(([fieldKey, data]) => {
            let element = null;
            try {
                // Try to find element by name or id, with proper escaping
                const escapedFieldKey = fieldKey.replace(/[\[\]]/g, "\\\\$&");
                element = document.querySelector(`[name="${escapedFieldKey}"], #${escapedFieldKey}`);
            } catch (e) {
                // If selector is invalid, try alternative approaches
                console.warn("Invalid selector for field:", fieldKey, "trying alternatives...");
                try {
                    element = document.querySelector(`[name="${fieldKey}"]`) || document.getElementById(fieldKey);
                } catch (e2) {
                    console.warn("Could not find element for field:", fieldKey);
                }
            }
            
            if (element && !element.value) {
                try {
                    if (element.tagName === "SELECT") {
                        const option = Array.from(element.options).find(opt => 
                            opt.text === data.response || opt.value === data.response
                        );
                        if (option) {
                            element.selectedIndex = option.index;
                            element.dispatchEvent(new Event("change", { bubbles: true }));
                            console.log("✅ Restored select field:", fieldKey, "=", data.response);
                        }
                    } else if (element.type === "checkbox" || element.type === "radio") {
                        element.checked = !!data.response;
                        element.dispatchEvent(new Event("change", { bubbles: true }));
                        console.log("✅ Restored checkbox/radio:", fieldKey, "=", data.response);
                    } else {
                        element.value = data.response;
                        element.dispatchEvent(new Event("input", { bubbles: true }));
                        element.dispatchEvent(new Event("change", { bubbles: true }));
                        console.log("✅ Restored text field:", fieldKey, "=", data.response);
                    }
                } catch (e) {
                    console.warn("Failed to restore field:", fieldKey, e);
                }
            }
        });
    }
    
    // Override get_base_domain function to prevent domain assignment errors
    if (typeof window.get_base_domain === "function" || typeof get_base_domain === "function") {
        console.log("Found get_base_domain function, overriding...");
        window.get_base_domain = function() {
            console.log("get_base_domain() called - returning current hostname to prevent sandbox error");
            return window.location.hostname;
        };
        // Also set global version in case it is accessed without window
        if (typeof get_base_domain !== "undefined") {
            get_base_domain = window.get_base_domain;
        }
        console.log("Successfully overrode get_base_domain function");
    } else {
        // Proactively define it in case the script loads later
        window.get_base_domain = function() {
            console.log("get_base_domain() called (proactive override) - returning current hostname");
            return window.location.hostname;
        };
        console.log("Proactively defined get_base_domain function");
    }
    
    // Add error handling for domain security issues
    window.addEventListener("error", function(e) {
        if (e.message && e.message.includes("domain")) {
            console.warn("Domain security error detected:", e.message);
            console.log("This is expected in iframe mode and will not prevent auto-fill functionality");
        }
    });
    
    window.addEventListener("message", function(event) {
        console.log("🔔 IFRAME: Received message:", event.data);
        console.log("🔍 IFRAME: Message type:", typeof event.data, event.data && event.data.type);
        console.log("🔍 IFRAME: Has formData:", !!(event.data && event.data.formData));
        
        if (event.data && event.data.type === "APPLIFLOW_AUTOFILL") {
            console.log("🎯 IFRAME: Processing auto-fill request...");
            console.log("📝 IFRAME: Form data received:", event.data.formData);
            console.log("⚙️ IFRAME: Auto-fill data received:", event.data.autoFillData);
            
            // Set user ID for learning system
            LEARNING_CONFIG.userId = event.data.userId || getCurrentUserId();
            if (LEARNING_CONFIG.userId) {
                sessionStorage.setItem("appliflow_user_id", LEARNING_CONFIG.userId.toString());
            }
            
            fillBasicFields(event.data.formData, event.data.autoFillData);
        } else {
            console.log("🔇 IFRAME: Ignoring message. Type:", event.data && event.data.type, "Data:", event.data);
        }
    });
    
    function fillBasicFields(formData, autoFillData) {
        let fieldsFilledCount = 0;
        
        console.log("🔥 === AppliFlow Auto-fill Debug ===");
        console.log("📋 Form data received:", formData);
        console.log("🤖 Auto-fill data:", autoFillData);
        console.log("🌐 Current URL:", window.location.href);
        
        // Mark fields as filled by AppliFlow to exclude from learning
        function markFieldAsFilled(element) {
            if (element) {
                element.dataset.appliflowFilled = "true";
                // Add purple highlighting to show auto-filled fields
                element.style.backgroundColor = "#f3f2ff"; // Light purple background
                element.style.border = "2px solid #4b38f1"; // Purple border (logout button color)
            }
        }
        
        // Try analyzed selectors first if available
        if (autoFillData && autoFillData.has_structure && autoFillData.form_fields) {
            console.log("✓ Using analyzed field selectors for " + autoFillData.platform_name);
            console.log("Available form fields:", Object.keys(autoFillData.form_fields));
            
            for (const [fieldKey, fieldInfo] of Object.entries(autoFillData.form_fields)) {
                const dataSource = fieldInfo.data_source;
                let value = null;
                
                if (dataSource === "personal.first_name") value = formData.firstName;
                else if (dataSource === "personal.last_name") value = formData.lastName;
                else if (dataSource === "personal.email") value = formData.email;
                else if (dataSource === "personal.phone") value = formData.phone;
                
                console.log("Processing field " + fieldKey + " with data source " + dataSource + " = " + value);
                
                if (!value) {
                    console.log("❌ No value for " + fieldKey);
                    continue;
                }
                
                for (const selector of fieldInfo.selectors || []) {
                    try {
                        const field = document.querySelector(selector);
                        console.log("Trying selector: " + selector + " -> " + (field ? "found" : "not found"));
                        
                        if (field && field.type !== "hidden") {
                            field.value = value;
                            field.dispatchEvent(new Event("input", { bubbles: true }));
                            field.dispatchEvent(new Event("change", { bubbles: true }));
                            markFieldAsFilled(field);
                            console.log("✅ Filled " + fieldKey + " with " + value + " using " + selector);
                            fieldsFilledCount++;
                            break;
                        }
                    } catch (e) {
                        console.warn("❌ Selector failed:", selector, e);
                    }
                }
            }
        } else {
            console.log("⚠️ No analyzed structure available, using fallback selectors");
            console.log("Has autoFillData:", !!autoFillData);
            console.log("Has structure:", autoFillData?.has_structure);
            console.log("Has form_fields:", !!autoFillData?.form_fields);
            
            // Show all input fields on the page for debugging
            const allInputs = document.querySelectorAll("input");
            console.log("Found " + allInputs.length + " input fields on page:");
            allInputs.forEach(function(input, index) {
                console.log("Input " + index + ":", {
                    name: input.name,
                    id: input.id,
                    type: input.type,
                    placeholder: input.placeholder,
                    className: input.className
                });
            });
            
            // Fallback basic field mapping with Resumator-specific selectors
            const mappings = [
                {data: formData.firstName, name: "firstName", selectors: [
                    "input[name*=first]", "input[id*=first]", "input[placeholder*=first]",
                    "input[name*=resumator-firstname]", "input[id*=resumator-firstname]"
                ]},
                {data: formData.lastName, name: "lastName", selectors: [
                    "input[name*=last]", "input[id*=last]", "input[placeholder*=last]",
                    "input[name*=resumator-lastname]", "input[id*=resumator-lastname]"
                ]},
                {data: formData.email, name: "email", selectors: [
                    "input[type=email]", "input[name*=email]", "input[id*=email]",
                    "input[name*=resumator-email]", "input[id*=resumator-email]"
                ]},
                {data: formData.phone, name: "phone", selectors: [
                    "input[type=tel]", "input[name*=phone]", "input[id*=phone]",
                    "input[name*=resumator-phone]", "input[id*=resumator-phone]"
                ]},
                {data: formData.address, name: "address", selectors: [
                    "input[name*=address]", "input[id*=address]", "input[placeholder*=address]",
                    "input[name*=resumator-address]", "input[id*=resumator-address]"
                ]},
                {data: formData.city, name: "city", selectors: [
                    "input[name*=city]", "input[id*=city]", "input[placeholder*=city]",
                    "input[name*=resumator-city]", "input[id*=resumator-city]"
                ]},
                {data: formData.state, name: "state", selectors: [
                    "input[name*=state]", "input[id*=state]", "input[placeholder*=state]",
                    "input[name*=resumator-state]", "input[id*=resumator-state]"
                ]},
                {data: formData.zip, name: "zip", selectors: [
                    "input[name*=zip]", "input[id*=zip]", "input[placeholder*=zip]",
                    "input[name*=postal]", "input[id*=postal]", "input[placeholder*=postal]",
                    "input[name*=resumator-postal]", "input[id*=resumator-postal]"
                ]}
            ];
            
            mappings.forEach(function(mapping) {
                if (!mapping.data) {
                    console.log("❌ No data for " + mapping.name);
                    return;
                }
                
                console.log("Trying to fill " + mapping.name + " with " + mapping.data);
                
                mapping.selectors.forEach(function(selector) {
                    const field = document.querySelector(selector);
                    console.log("Selector " + selector + " -> " + (field ? "found" : "not found"));
                    
                    if (field && field.type !== "hidden" && !field.value) {
                        field.value = mapping.data;
                        field.dispatchEvent(new Event("input", { bubbles: true }));
                        field.dispatchEvent(new Event("change", { bubbles: true }));
                        markFieldAsFilled(field);
                        console.log("✅ Filled " + mapping.name + " with " + mapping.data + " using " + selector);
                        fieldsFilledCount++;
                        return;
                    }
                });
            });
        }
        
        console.log("Auto-fill completed: " + fieldsFilledCount + " fields filled");
        
        if (window.parent !== window) {
            window.parent.postMessage({
                type: "APPLIFLOW_AUTOFILL_COMPLETE",
                fieldsFilledCount: fieldsFilledCount,
                platform: (autoFillData && autoFillData.platform_name) || "Unknown"
            }, "*");
        }
    }
    
    // Get the original domain from current URL
    const originalDomain = "' . $originalDomain . '";
    const originalScheme = "' . $originalScheme . '";
    
    // Function to rewrite URLs in the page to use proxy
    function setupNavigationInterception() {
        console.log("🔧 Setting up URL rewriting...");
        console.log("🌐 Original domain:", originalDomain);
        console.log("🔗 App URL:", "' . $appUrl . '");
        
        // Rewrite all relative URLs on the page to use the proxy
        rewriteRelativeUrls();
        
        // Set up a mutation observer to handle dynamically added content
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === "childList") {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === Node.ELEMENT_NODE) {
                            rewriteUrlsInElement(node);
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        console.log("✅ URL rewriting initialized");
    }
    
    function rewriteRelativeUrls() {
        console.log("🔄 Rewriting relative URLs on the page...");
        rewriteUrlsInElement(document);
    }
    
    function rewriteUrlsInElement(element) {
        const appUrl = "' . $appUrl . '";
        
        // Rewrite anchor href attributes
        const links = element.querySelectorAll ? element.querySelectorAll("a[href]") : [];
        links.forEach(function(link) {
            const href = link.getAttribute("href");
            if (href && href.startsWith("/") && !href.startsWith("//")) {
                const absoluteUrl = originalScheme + "://" + originalDomain + href;
                const proxyUrl = appUrl + "/job-proxy?url=" + encodeURIComponent(absoluteUrl);
                link.setAttribute("href", proxyUrl);
                console.log("🔗 Rewrote link:", href, "→", proxyUrl);
            }
        });
        
        // Rewrite form action attributes
        const forms = element.querySelectorAll ? element.querySelectorAll("form[action]") : [];
        forms.forEach(function(form) {
            const action = form.getAttribute("action");
            if (action && action.startsWith("/") && !action.startsWith("//")) {
                const absoluteUrl = originalScheme + "://" + originalDomain + action;
                const proxyUrl = appUrl + "/job-proxy?url=" + encodeURIComponent(absoluteUrl);
                form.setAttribute("action", proxyUrl);
                console.log("📝 Rewrote form action:", action, "→", proxyUrl);
            }
        });
    }
    
    // Initialize learning system when page loads
    document.addEventListener("DOMContentLoaded", function() {
        try {
            console.log("🚀 DOM loaded - initializing systems");
            initializeLearningMonitor();
            initializeAutoFill();
            setupNavigationInterception();
            
            // Restore form data after a short delay to allow dynamic content to load
            setTimeout(function() {
                try {
                    restoreFormData();
                } catch (e) {
                    console.warn("Form data restoration failed:", e);
                }
            }, 500);
        } catch (e) {
            console.error("Failed to initialize AppliFlow systems:", e);
        }
    });
    
    // Also initialize if DOM is already loaded
    if (document.readyState === "complete" || document.readyState === "interactive") {
        try {
            console.log("🚀 DOM already loaded - initializing systems immediately");
            initializeLearningMonitor();
            initializeAutoFill();
            setupNavigationInterception();
            setTimeout(function() {
                try {
                    restoreFormData();
                } catch (e) {
                    console.warn("Form data restoration failed:", e);
                }
            }, 500);
        } catch (e) {
            console.error("Failed to initialize AppliFlow systems:", e);
        }
    }
    
    setTimeout(function() {
        if (window.parent !== window) {
            window.parent.postMessage({
                type: "APPLIFLOW_PROXY_READY"
            }, "*");
        }
    }, 1000);
})();
';
        
        $script->appendChild($dom->createTextNode($jsCode));
        
        // Append to head or body
        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head) {
            $head->appendChild($script);
        } else {
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body) {
                $body->appendChild($script);
            }
        }
    }
    
    /**
     * Post-process HTML with string operations
     */
    private function postProcessHtml(string $html, string $baseUrl): string
    {
        // Remove or modify any remaining frame-busting scripts
        $html = preg_replace('/if\s*\(\s*top\s*[!=]==?\s*self\s*\).*?}/si', '', $html);
        $html = preg_replace('/if\s*\(\s*parent\s*[!=]==?\s*self\s*\).*?}/si', '', $html);
        
        // Remove document.domain assignments that cause sandbox security errors
        $html = preg_replace('/document\.domain\s*=.*?;/si', '', $html);
        $html = preg_replace('/window\.document\.domain\s*=.*?;/si', '', $html);
        
        // Add base tag if not present
        if (!str_contains($html, '<base')) {
            $parsedUrl = parse_url($baseUrl);
            $baseHref = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            if (isset($parsedUrl['path'])) {
                $baseHref .= dirname($parsedUrl['path']) . '/';
            }
            
            $html = str_replace('<head>', '<head><base href="' . $baseHref . '">', $html);
        }
        
        return $html;
    }
}