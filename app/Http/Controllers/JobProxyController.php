<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid URL format'], 400);
        }
        
        // Security: Only allow specific job sites
        $allowedDomains = [
            'ashbyhq.com',
            'greenhouse.io', 
            'lever.co',
            'workday.com',
            'icims.com',
            'bamboohr.com',
            'smartrecruiters.com',
            'jobvite.com',
            'theresumator.com',
            'resumator.com',
            'applytojob.com'
        ];
        
        $parsedUrl = parse_url($url);
        $domain = $parsedUrl['host'] ?? '';
        
        $isAllowed = false;
        foreach ($allowedDomains as $allowedDomain) {
            if (str_ends_with($domain, $allowedDomain)) {
                $isAllowed = true;
                break;
            }
        }
        
        if (!$isAllowed) {
            return response()->json(['error' => 'Domain not allowed'], 403);
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
        $this->injectCustomScripts($dom);
        
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
    private function injectCustomScripts(DOMDocument $dom): void
    {
        $script = $dom->createElement('script');
        $script->setAttribute('type', 'text/javascript');
        
        // Create basic auto-fill JavaScript
        $jsCode = '
(function() {
    console.log("AppliFlow auto-fill script loaded on URL:", window.location.href);
    
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