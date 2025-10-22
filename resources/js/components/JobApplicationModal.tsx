import { useState, useEffect } from 'react';
import { X, ExternalLink, Loader2, CheckCircle, AlertTriangle, SkipForward } from 'lucide-react';
import { Button } from './ui/button';

interface FormData {
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  linkedinUrl: string;
  portfolioUrl: string;
  currentLocation: string;
  experience: Array<{
    company: string;
    position: string;
    startDate: string;
    endDate: string;
    isCurrent: boolean;
  }>;
}

interface JobApplicationModalProps {
  isOpen: boolean;
  onClose: () => void;
  jobTitle: string;
  company: string;
  applicationUrl: string;
  onApplicationComplete: () => void;
  onSkipJob?: () => void;
  userFormData?: FormData;
  currentJobIndex?: number;
  totalJobs?: number;
  skipAutoStart?: boolean; // If true, don't automatically call API when modal opens
}

type AutomationState = 'analyzing' | 'needs_input' | 'filling' | 'ready_to_submit' | 'submitting' | 'completed' | 'error';

interface AutomationResult {
  status: string;
  filled_fields?: string[];
  session_id?: string;
  lead_id?: number;
  application_id?: number;
  preferences_saved?: number;
  missing_fields?: Array<{
    semantic_field: string;
    label: string;
    type: string;
    required: boolean;
    suggested_answer?: string;
    options?: Array<{
      value: string;
      label: string;
    }>;
  }>;
  error?: string;
  requires_captcha?: boolean;
  message?: string;
}

export function JobApplicationModal({
  isOpen,
  onClose,
  jobTitle,
  company,
  applicationUrl,
  onApplicationComplete,
  onSkipJob,
  userFormData,
  currentJobIndex,
  totalJobs,
  skipAutoStart = false
}: JobApplicationModalProps) {
  const [automationState, setAutomationState] = useState<AutomationState>('analyzing');
  const [automationResult, setAutomationResult] = useState<AutomationResult | null>(null);
  const [sessionId, setSessionId] = useState<string>('');
  const [missingFields, setMissingFields] = useState<AutomationResult['missing_fields']>([]);
  const [missingFieldValues, setMissingFieldValues] = useState<Record<string, string>>({});
  const [showSkipButton, setShowSkipButton] = useState(false);
  const [isSubmittingMissingFields, setIsSubmittingMissingFields] = useState(false);
  const [secondsUntilNext, setSecondsUntilNext] = useState(5);

  // Auto-scroll to modal when opened
  useEffect(() => {
    if (isOpen) {
      // Small delay to ensure modal is rendered
      setTimeout(() => {
        const modalElement = document.querySelector('[data-modal="job-application"]');
        if (modalElement) {
          modalElement.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
          });
        }
      }, 100);
    }
  }, [isOpen]);

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      setAutomationState('analyzing');
      setAutomationResult(null);
      setSessionId('');
      setMissingFields([]);
      setMissingFieldValues({});
      setShowSkipButton(false);
      setIsSubmittingMissingFields(false);

      // Start automation only if not skipping auto-start
      if (!skipAutoStart) {
        startAutomation();
      }
    }
  }, [isOpen, applicationUrl, skipAutoStart]);

  // Show skip button after 30 seconds if still analyzing or immediately if error
  useEffect(() => {
    if (automationState === 'analyzing') {
      const timeout = setTimeout(() => {
        setShowSkipButton(true);
      }, 30000);
      
      return () => clearTimeout(timeout);
    } else if (automationState === 'error') {
      setShowSkipButton(true);
    }
  }, [automationState]);

  const handleAutomationResult = (result: AutomationResult) => {
    setAutomationResult(result);

    // Handle different result statuses
    if (result.status === 'insufficient_credits') {
      // Out of credits - redirect to subscribe page
      window.location.href = '/subscribe';
      return;
    } else if (result.status === 'needs_user_input') {
      setMissingFields(result.missing_fields || []);
      setSessionId(result.session_id || '');
      setAutomationState('needs_input');
    } else if (result.status === 'ready_to_submit') {
      setSessionId(result.session_id || '');
      setAutomationState('ready_to_submit');
    } else if (result.status === 'processing') {
      // Backend is processing asynchronously, start polling
      const sessionKey = (result as any).session_key;
      if (sessionKey) {
        pollAutomationStatus(sessionKey);
      }
    } else if (result.status === 'submitted') {
      // Email-based application completed successfully
      // Immediately call onApplicationComplete to show success on job card
      onApplicationComplete();
    } else if (result.status === 'error' || result.status === 'failed') {
      setAutomationState('error');
    } else {
      setAutomationState('error');
    }
  };

  const pollAutomationStatus = async (sessionKey: string) => {
    const pollInterval = setInterval(async () => {
      try {
        const response = await fetch(`/api/automation/status/${sessionKey}`, {
          credentials: 'include',
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const status = await response.json();

        // Handle array response (backend returns array with single object)
        const statusData = Array.isArray(status) ? status[0] : status;

        // Update status message if available
        if (statusData.message) {
          // Status message available
        }

        // Check if completed
        if (statusData.status !== 'queued' && statusData.status !== 'processing') {
          clearInterval(pollInterval);
          handleAutomationResult(statusData);
        }

      } catch (error) {
        console.error('Status polling failed:', error);
        clearInterval(pollInterval);
        setAutomationState('error');
        setAutomationResult({
          status: 'error',
          error: 'Failed to check automation status'
        });
      }
    }, 2000); // Poll every 2 seconds
    
    // Stop polling after 5 minutes
    const timeoutId = setTimeout(() => {
      clearInterval(pollInterval);
      setAutomationState('error');
      setAutomationResult({ 
        status: 'error',
        error: 'Operation timed out after 5 minutes. Please try again.' 
      });
    }, 300000); // 5 minutes
    
    // Return cleanup function
    return () => {
      clearInterval(pollInterval);
      clearTimeout(timeoutId);
    };
  };

  const startAutomation = async () => {
    try {
      setAutomationState('analyzing');

      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      // Call email-based automation endpoint
      const response = await fetch('/api/automation/process-email-application', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'include',
        body: JSON.stringify({
          job_url: applicationUrl
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      // Handle immediate result (no polling needed for email-based)
      setAutomationResult(result);
      handleAutomationResult(result);

    } catch (error) {
      console.error('Automation failed:', error);
      setAutomationState('error');
      setAutomationResult({
        status: 'error',
        error: (error as Error).message
      });
    }
  };

  const handleMissingFieldChange = (semanticField: string, value: string) => {
    setMissingFieldValues(prev => ({
      ...prev,
      [semanticField]: value
    }));
  };

  const submitMissingFields = async () => {
    try {
      setIsSubmittingMissingFields(true);

      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      // Extract lead_id from the automationResult (added by our new endpoint)
      const leadId = automationResult?.lead_id;

      const response = await fetch('/api/automation/submit-preferences-and-apply', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'include',
        body: JSON.stringify({
          lead_id: leadId,
          missing_field_values: missingFieldValues
        })
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (result.status === 'submitted') {
        // Immediately call onApplicationComplete to show success on job card
        onApplicationComplete();
      } else {
        setAutomationResult(result);
        setAutomationState('error');
      }

      setIsSubmittingMissingFields(false);

    } catch (error) {
      console.error('Failed to submit missing fields:', error);
      setIsSubmittingMissingFields(false);
      setAutomationState('error');
      setAutomationResult({
        status: 'error',
        error: (error as Error).message
      });
    }
  };

  const submitApplication = async () => {
    try {
      setAutomationState('submitting');
      
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();
      
      const response = await fetch('/api/automation/submit-application', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'include',
        body: JSON.stringify({
          session_id: sessionId
        })
      });
      
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      
      const result = await response.json();
      setAutomationResult(result);

      if (result.status === 'completed') {
        // Immediately call onApplicationComplete to show success on job card
        onApplicationComplete();
      } else if (result.status === 'requires_captcha') {
        setAutomationState('error');
        setAutomationResult({
          status: 'error',
          error: 'Captcha detected. Please complete the application manually.',
          requires_captcha: true
        });
      } else {
        setAutomationState('error');
      }
      
    } catch (error) {
      console.error('Failed to submit application:', error);
      setAutomationState('error');
      setAutomationResult({ 
        status: 'error',
        error: (error as Error).message 
      });
    }
  };

  const openInNewTab = () => {
    window.open(applicationUrl, '_blank');
  };

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center pt-4 pb-4 overflow-y-auto">
      {/* Backdrop */}
      <div 
        className="absolute inset-0 bg-black/50 backdrop-blur-sm"
        onClick={onClose}
      />
      
      {/* Modal */}
      <div 
        data-modal="job-application"
        className="relative w-full max-w-4xl min-h-[600px] mx-4 bg-white rounded-lg shadow-2xl flex flex-col"
      >
        {/* Header */}
        <div className="p-4 border-b border-gray-200">
          <div className="flex items-center justify-between mb-3">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-[#2D5BFF] rounded-lg flex items-center justify-center">
                <span className="text-white font-bold text-sm">AF</span>
              </div>
              <div>
                <h2 className="text-lg font-semibold text-gray-900">
                  Applying to {jobTitle}
                </h2>
                <p className="text-sm text-gray-600">
                  {company}
                  {currentJobIndex !== undefined && totalJobs && (
                    <span className="ml-2 text-xs text-gray-500">
                      ({currentJobIndex + 1} of {totalJobs})
                    </span>
                  )}
                </p>
              </div>
            </div>
            
            <Button
              variant="ghost"
              size="sm"
              onClick={onClose}
              className="p-2"
            >
              <X className="h-5 w-5" />
            </Button>
          </div>
          
          {/* Action Buttons */}
          <div className="flex items-center gap-2">
            {onSkipJob && showSkipButton && (
              <Button
                variant="outline"
                size="sm"
                onClick={onSkipJob}
                className="flex items-center gap-2 text-orange-600 border-orange-200 hover:bg-orange-50"
              >
                <SkipForward className="h-4 w-4" />
                Skip to Next Job
              </Button>
            )}
            <Button
              variant="outline"
              size="sm"
              onClick={openInNewTab}
              className="flex items-center gap-2"
            >
              <ExternalLink className="h-4 w-4" />
              Open in New Tab
            </Button>
          </div>
        </div>

        {/* Status Bar */}
        <div className="px-4 py-3 bg-gray-50 border-b border-gray-200">
          <div className="flex items-center gap-3">
            {automationState === 'analyzing' && (
              <>
                <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                <span className="text-sm font-medium text-blue-600">Analyzing form with AI...</span>
              </>
            )}
            {automationState === 'needs_input' && (
              <>
                <AlertTriangle className="h-4 w-4 text-orange-600" />
                <span className="text-sm font-medium text-orange-600">Additional information needed</span>
              </>
            )}
            {automationState === 'filling' && (
              <>
                <Loader2 className="h-4 w-4 animate-spin text-green-600" />
                <span className="text-sm font-medium text-green-600">Filling out application...</span>
              </>
            )}
            {automationState === 'ready_to_submit' && (
              <>
                <CheckCircle className="h-4 w-4 text-green-600" />
                <span className="text-sm font-medium text-green-600">Application ready for submission</span>
              </>
            )}
            {automationState === 'submitting' && (
              <>
                <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                <span className="text-sm font-medium text-blue-600">Submitting application...</span>
              </>
            )}
            {automationState === 'completed' && (
              <>
                <CheckCircle className="h-4 w-4 text-green-600" />
                <span className="text-sm font-medium text-green-600">Application submitted successfully!</span>
              </>
            )}
            {automationState === 'error' && (
              <>
                <AlertTriangle className="h-4 w-4 text-red-600" />
                <span className="text-sm font-medium text-red-600">Technical issue - Let's come back to this one later</span>
              </>
            )}
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 p-6">
          {/* Analyzing State */}
          {automationState === 'analyzing' && (
            <div className="h-full flex items-center justify-center">
              <div className="text-center">
                <div className="relative mb-6">
                  <div className="w-16 h-16 mx-auto">
                    <div className="absolute inset-0 border-4 border-blue-200 rounded-full animate-pulse"></div>
                    <div className="absolute inset-2 border-4 border-purple-300 rounded-full animate-spin"></div>
                    <div className="absolute inset-4 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full animate-bounce"></div>
                  </div>
                </div>
                <h3 className="text-xl font-semibold text-gray-800 mb-2">Analyzing Job Requirements</h3>
                <p className="text-gray-600 mb-2">Our AI is discovering what information this employer needs...</p>
                <div className="flex items-center justify-center space-x-2 text-sm text-gray-500">
                  <span>AI Analysis</span>
                  <span>•</span>
                  <span>Smart Detect Defaults</span>
                </div>
              </div>
            </div>
          )}

          {/* Missing Input State */}
          {automationState === 'needs_input' && (
            <div className="max-w-2xl mx-auto">
              <div className="text-center mb-6">
                <div className="w-12 h-12 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                  <AlertTriangle className="h-6 w-6 text-orange-600" />
                </div>
                <h3 className="text-lg font-semibold text-gray-900 mb-2">Additional Information Needed</h3>
                <p className="text-gray-600 mb-4">
                  Please provide the following information to complete your application:
                </p>
              </div>
              
              <div className="space-y-4">
                {missingFields?.map((field) => (
                  <div key={field.semantic_field} className="space-y-2">
                    <label className="block text-sm font-medium text-gray-700">
                      {field.label}
                      {field.required && <span className="text-red-500 ml-1">*</span>}
                    </label>
                    {field.type === 'select' ? (
                      <select
                        value={missingFieldValues[field.semantic_field] || ''}
                        onChange={(e) => handleMissingFieldChange(field.semantic_field, e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                      >
                        <option value="">Select an option</option>
                        {/* Use options from backend if available */}
                        {field.options && field.options.map((option) => (
                          <option key={option.value} value={option.value}>
                            {option.label}
                          </option>
                        ))}
                        {/* Fallback for legacy hardcoded options */}
                        {!field.options && field.semantic_field === 'work_authorization' && (
                          <>
                            <option value="Yes">Yes, I am authorized to work</option>
                            <option value="No">No, I need sponsorship</option>
                          </>
                        )}
                        {!field.options && field.semantic_field === 'visa_sponsorship' && (
                          <>
                            <option value="No">No, I do not require sponsorship</option>
                            <option value="Yes">Yes, I require sponsorship</option>
                          </>
                        )}
                      </select>
                    ) : (
                      <div className="space-y-2">
                        <input
                          type={field.type}
                          value={missingFieldValues[field.semantic_field] || ''}
                          onChange={(e) => handleMissingFieldChange(field.semantic_field, e.target.value)}
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                          placeholder={`Enter your ${field.label.toLowerCase()}`}
                        />
                        {field.suggested_answer && (
                          <div className="text-xs text-gray-500">
                            <span className="font-medium">Suggested:</span> {field.suggested_answer}
                            <button
                              type="button"
                              onClick={() => handleMissingFieldChange(field.semantic_field, field.suggested_answer || '')}
                              className="ml-2 text-purple-600 hover:text-purple-800 underline"
                            >
                              Use this
                            </button>
                          </div>
                        )}
                      </div>
                    )}
                  </div>
                ))}
              </div>
              
              <div className="mt-6 flex justify-end">
                <Button
                  onClick={submitMissingFields}
                  disabled={missingFields?.some(field => 
                    field.required && !missingFieldValues[field.semantic_field]
                  ) || isSubmittingMissingFields}
                  className="bg-purple-600 hover:bg-purple-700 disabled:bg-gray-400 text-white px-6 py-2 rounded disabled:opacity-50 disabled:cursor-not-allowed border-0"
                  style={{ backgroundColor: isSubmittingMissingFields ? '#9CA3AF' : '#7C3AED', color: 'white' }}
                >
                  {isSubmittingMissingFields ? 'Processing...' : 'Continue Application'}
                </Button>
              </div>
            </div>
          )}

          {/* Filling State */}
          {automationState === 'filling' && (
            <div className="h-full flex items-center justify-center">
              <div className="text-center">
                <Loader2 className="h-12 w-12 animate-spin text-green-600 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-800 mb-2">Filling Out Application</h3>
                <p className="text-gray-600">Automatically completing the form...</p>
              </div>
            </div>
          )}

          {/* Ready to Submit State */}
          {automationState === 'ready_to_submit' && (
            <div className="text-center">
              <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <CheckCircle className="h-8 w-8 text-green-600" />
              </div>
              <h3 className="text-xl font-semibold text-gray-800 mb-2">Application Ready!</h3>
              <p className="text-gray-600 mb-6">
                Your application has been automatically filled out and is ready for submission.
              </p>
              
              {automationResult?.filled_fields && (
                <div className="mb-6 p-4 bg-white rounded-lg shadow-sm max-w-md mx-auto">
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Fields Completed:</h4>
                  <div className="text-sm text-gray-600 space-y-1">
                    {automationResult.filled_fields.slice(0, 5).map((field: string, index: number) => (
                      <div key={index} className="flex items-center">
                        <CheckCircle className="h-3 w-3 text-green-500 mr-2 flex-shrink-0" />
                        <span className="truncate">{field}</span>
                      </div>
                    ))}
                    {automationResult.filled_fields.length > 5 && (
                      <div className="text-xs text-gray-500">
                        +{automationResult.filled_fields.length - 5} more fields
                      </div>
                    )}
                  </div>
                </div>
              )}
              
              <Button
                onClick={submitApplication}
                className="bg-green-600 hover:bg-green-700 text-white px-8 py-3 text-lg font-medium"
              >
                🚀 Submit Application
              </Button>
            </div>
          )}

          {/* Submitting State */}
          {automationState === 'submitting' && (
            <div className="h-full flex items-center justify-center">
              <div className="text-center">
                <Loader2 className="h-12 w-12 animate-spin text-blue-600 mx-auto mb-4" />
                <h3 className="text-xl font-semibold text-gray-800 mb-2">Submitting Application...</h3>
                <p className="text-gray-600">Please wait while we complete your application submission.</p>
              </div>
            </div>
          )}

          {/* Completed State */}
          {automationState === 'completed' && (
            <div className="h-full flex items-center justify-center">
              <div className="text-center">
                <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                  <CheckCircle className="h-10 w-10 text-green-600" />
                </div>
                <h3 className="text-2xl font-bold text-green-800 mb-2">Application Submitted! 🎉</h3>
                <p className="text-green-700 mb-4">
                  Your application has been successfully submitted to {company}.
                </p>
                {currentJobIndex !== undefined && totalJobs !== undefined && currentJobIndex < totalJobs - 1 ? (
                  <p className="text-sm text-gray-600">
                    Moving to next job in {secondsUntilNext} second{secondsUntilNext !== 1 ? 's' : ''}...
                  </p>
                ) : (
                  <p className="text-sm text-gray-600">
                    All applications complete!
                  </p>
                )}
              </div>
            </div>
          )}

          {/* Error State */}
          {automationState === 'error' && (
            <div className="text-center max-w-md mx-auto">
              <div className="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <AlertTriangle className="h-8 w-8 text-orange-600" />
              </div>
              <h3 className="text-xl font-semibold text-gray-800 mb-2">Minor Technical Issue</h3>
              <p className="text-gray-600 mb-4">
                We encountered a small hiccup with this application. Don't worry - we'll come back to it later!
              </p>

              {automationResult?.requires_captcha && (
                <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                  <p className="text-sm text-blue-800">
                    This employer uses additional verification. We'll handle this one separately.
                  </p>
                </div>
              )}
              
              <div className="space-y-2">
                <Button
                  onClick={openInNewTab}
                  className="w-full bg-blue-600 hover:bg-blue-700 text-white"
                >
                  Open Application in New Tab
                </Button>
                <Button
                  onClick={startAutomation}
                  variant="outline"
                  className="w-full"
                >
                  Try Again
                </Button>
              </div>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-gray-200 bg-gray-50">
          <div className="flex items-center justify-between text-sm text-gray-600">
            <div className="flex items-center gap-4">
              {automationState === 'error' ? (
                <>
                  <span>We'll revisit this one later</span>
                </>
              ) : (
                <>
                  <span>AI-powered application</span>
                  <span>Gmail API integration</span>
                  <span>🔒 Secure & personal</span>
                </>
              )}
            </div>
            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                onClick={onClose}
                size="sm"
              >
                Cancel
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}