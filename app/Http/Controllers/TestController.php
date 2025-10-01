<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestController extends Controller
{
    /**
     * Show a test form for the learning system
     */
    public function showTestForm(Request $request)
    {
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="' . csrf_token() . '">
    <title>AppliFlow Learning System Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .debug { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>AppliFlow Learning System Test</h1>
    <p>This form contains various types of fields to test the learning system. Fill out the fields and watch the console for learning events.</p>
    
    <div class="debug">
        <strong>Debug Info:</strong><br>
        Current URL: <span id="current-url"></span><br>
        User ID: <span id="user-id">Loading...</span><br>
        Learning System Status: <span id="learning-status">Initializing...</span>
    </div>

    <form id="test-form">
        <!-- Basic fields that should be auto-filled -->
        <div class="form-group">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" placeholder="Should be auto-filled">
        </div>

        <div class="form-group">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name" placeholder="Should be auto-filled">
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" placeholder="Should be auto-filled">
        </div>

        <div class="form-group">
            <label for="phone">Phone Number</label>
            <input type="tel" id="phone" name="phone" placeholder="Should be auto-filled">
        </div>

        <!-- Unknown fields that should trigger learning -->
        <div class="form-group">
            <label for="work_authorization">Are you currently authorized to work in the United States on a full-time basis for any employer without restriction?</label>
            <select id="work_authorization" name="work_authorization">
                <option value="">Please select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>

        <div class="form-group">
            <label for="visa_sponsorship">Do you now or will you in the future require visa sponsorship?</label>
            <select id="visa_sponsorship" name="visa_sponsorship">
                <option value="">Please select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>

        <div class="form-group">
            <label for="background_check">Are you willing to submit to a background check?</label>
            <div>
                <input type="radio" id="background_yes" name="background_check" value="yes">
                <label for="background_yes" style="display: inline; font-weight: normal;">Yes</label>
            </div>
            <div>
                <input type="radio" id="background_no" name="background_check" value="no">
                <label for="background_no" style="display: inline; font-weight: normal;">No</label>
            </div>
        </div>

        <div class="form-group">
            <label for="remote_work">Are you open to remote work opportunities?</label>
            <input type="checkbox" id="remote_work" name="remote_work" value="yes">
            <label for="remote_work" style="display: inline; font-weight: normal;">Yes, I am open to remote work</label>
        </div>

        <div class="form-group">
            <label for="cover_letter">Why are you interested in this position?</label>
            <textarea id="cover_letter" name="cover_letter" rows="4" placeholder="This should trigger learning when you type..."></textarea>
        </div>

        <div class="form-group">
            <label for="salary_expectation">What is your salary expectation?</label>
            <input type="text" id="salary_expectation" name="salary_expectation" placeholder="e.g., $75,000">
        </div>

        <button type="submit">Submit Application (Test)</button>
    </form>

    <div class="debug">
        <strong>Learning Events Log:</strong>
        <div id="learning-log" style="max-height: 200px; overflow-y: auto;"></div>
    </div>

    <script>
        // Debug utilities
        document.getElementById("current-url").textContent = window.location.href;
        
        // Log learning events
        const logDiv = document.getElementById("learning-log");
        function addLog(message) {
            const time = new Date().toLocaleTimeString();
            logDiv.innerHTML += `<div>[${time}] ${message}</div>`;
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        // Override console.log for learning events
        const originalLog = console.log;
        console.log = function(...args) {
            originalLog.apply(console, args);
            const message = args.join(" ");
            if (message.includes("🎯") || message.includes("Learning") || message.includes("AppliFlow") || message.includes("📝") || message.includes("Change")) {
                addLog(message);
            }
        };

        // Form submission handler
        document.getElementById("test-form").addEventListener("submit", function(e) {
            e.preventDefault();
            addLog("Form submission prevented - this is just a test");
            alert("Form submission prevented - this is just a test form for the learning system");
        });

        // Initialize learning system directly for test page
        initializeLearningSystem();
        
        // Initialize auto-fill system after DOM is loaded
        setTimeout(function() {
            initializeAutoFill();
        }, 100);
        
        // Update status when learning system loads
        setTimeout(function() {
            if (window.APPLIFLOW_LOADED) {
                document.getElementById("learning-status").textContent = "✅ Loaded";
                document.getElementById("learning-status").style.color = "green";
            } else {
                document.getElementById("learning-status").textContent = "❌ Not Loaded";
                document.getElementById("learning-status").style.color = "red";
            }
        }, 2000);
        
        // Global Learning System Configuration
        const LEARNING_CONFIG = {
            apiBase: "' . config('app.url') . '/api",
            userId: 1, // Test user ID
            sessionKey: "appliflow_form_data_" + window.location.host,
            enabled: true
        };
        
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
        
        // Helper function: Send form preference to learning API
        async function storeFormPreference(questionText, fieldType, responseData) {
                try {
                    console.log("🧠 Storing form preference:", { questionText, fieldType, responseData });
                    
                    // Get CSRF token from meta tag
                    const csrfToken = document.querySelector("meta[name=csrf-token]")?.getAttribute("content");
                    
                    const headers = {
                        "Content-Type": "application/json",
                        "X-Requested-With": "XMLHttpRequest"
                    };
                    
                    if (csrfToken) {
                        headers["X-CSRF-TOKEN"] = csrfToken;
                        console.log("🔐 Using CSRF token for request");
                    } else {
                        console.warn("⚠️ No CSRF token found");
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
                        const errorText = await response.text();
                        console.warn("❌ Failed to store form preference:", response.status, errorText);
                    }
                } catch (e) {
                    console.error("❌ Error storing form preference:", e);
                }
        }
        
        // Learning system initialization
        function initializeLearningSystem() {
            if (window.APPLIFLOW_LOADED) {
                console.log("AppliFlow already loaded, skipping...");
                return;
            }
            
            window.APPLIFLOW_LOADED = true;
            console.log("🎯 AppliFlow auto-fill script loaded on test page");
            
            // Monitor form field changes to capture learning data
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
                }
            });
        }
        
        // Auto-fill system to populate stored preferences
        async function initializeAutoFill() {
            console.log("🔄 Initializing auto-fill system...");
            
            try {
                // Fetch stored form preferences for this user
                const csrfToken = document.querySelector("meta[name=csrf-token]")?.getAttribute("content");
                
                const headers = {
                    "X-Requested-With": "XMLHttpRequest"
                };
                
                if (csrfToken) {
                    headers["X-CSRF-TOKEN"] = csrfToken;
                }
                
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
                        field.dataset.appliflowFilled = "true";
                        field.style.backgroundColor = "#f3f2ff"; // Light purple background
                        field.style.border = "2px solid #4b38f1"; // Purple border (logout button color)
                        
                        console.log("✅ Auto-filled field:", field.name || field.id, "with value:", responseData);
                    }
                });
            });
            
            console.log("🎉 Auto-fill completed:", filledCount, "fields populated");
            addLog("Auto-filled " + filledCount + " fields from stored preferences");
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
    </script>
</body>
</html>';

        return response($html)->header('Content-Type', 'text/html');
    }
}