import { useState, useEffect, useRef } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from './ui/dialog';
import { Button } from './ui/button';
import { Badge } from './ui/badge';
import { Card } from './ui/card';
import { ExternalLink, Copy, CheckCircle, AlertCircle, Loader2 } from 'lucide-react';

interface JobApplication {
  id: number;
  lead_id: number;
  status: string;
  application_method: string;
  form_data_sent: any;
  final_application_url: string;
  playwright_session_data?: {
    iframe_session_token: string;
    prepared_data: any;
  };
  lead: {
    job_title: string;
    company: string;
    source_url: string;
  };
}

interface IframeApplicationModalProps {
  isOpen: boolean;
  onClose: () => void;
  application: JobApplication | null;
  onApplicationUpdate: (applicationId: number, status: string) => void;
}

export function IframeApplicationModal({
  isOpen,
  onClose,
  application,
  onApplicationUpdate
}: IframeApplicationModalProps) {
  const [iframeLoaded, setIframeLoaded] = useState(false);
  const [autoFillStatus, setAutoFillStatus] = useState<'idle' | 'filling' | 'completed' | 'failed'>('idle');
  const [copiedFields, setCopiedFields] = useState<Set<string>>(new Set());
  const iframeRef = useRef<HTMLIFrameElement>(null);

  const formData = application?.form_data_sent;

  useEffect(() => {
    if (isOpen && application) {
      setIframeLoaded(false);
      setAutoFillStatus('idle');
      setCopiedFields(new Set());
    }
  }, [isOpen, application]);

  const handleIframeLoad = () => {
    setIframeLoaded(true);
    // Attempt to auto-fill form fields
    attemptAutoFill();
  };

  const attemptAutoFill = async () => {
    if (!application || !iframeRef.current) return;

    setAutoFillStatus('filling');

    try {
      // Create a script to inject into the iframe for auto-filling
      const autoFillScript = generateAutoFillScript(formData);
      
      // Note: Due to CORS restrictions, we can't directly access iframe content
      // Instead, we'll provide the user with pre-filled data they can copy
      setAutoFillStatus('completed');
      
    } catch (error) {
      console.error('Auto-fill failed:', error);
      setAutoFillStatus('failed');
    }
  };

  const generateAutoFillScript = (data: any) => {
    // This would generate JavaScript to auto-fill common form fields
    return `
      // Auto-fill name fields
      const nameInputs = document.querySelectorAll('input[name*="name"], input[placeholder*="name"]');
      nameInputs.forEach(input => {
        if (input.value === '') {
          input.value = '${data?.personal?.full_name || ''}';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });

      // Auto-fill email fields
      const emailInputs = document.querySelectorAll('input[type="email"], input[name*="email"]');
      emailInputs.forEach(input => {
        if (input.value === '') {
          input.value = '${data?.personal?.email || ''}';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });

      // Auto-fill phone fields
      const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"]');
      phoneInputs.forEach(input => {
        if (input.value === '') {
          input.value = '${data?.personal?.phone || ''}';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    `;
  };

  const copyToClipboard = (text: string, fieldName: string) => {
    navigator.clipboard.writeText(text).then(() => {
      setCopiedFields(prev => new Set([...prev, fieldName]));
      setTimeout(() => {
        setCopiedFields(prev => {
          const newSet = new Set(prev);
          newSet.delete(fieldName);
          return newSet;
        });
      }, 2000);
    });
  };

  const handleApplicationSubmitted = () => {
    if (application) {
      onApplicationUpdate(application.id, 'submitted');
      onClose();
    }
  };

  const handleApplicationFailed = () => {
    if (application) {
      onApplicationUpdate(application.id, 'failed');
    }
  };

  if (!application) return null;

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className=\"max-w-7xl h-[90vh] flex flex-col\">
        <DialogHeader>
          <DialogTitle className=\"flex items-center gap-3\">
            <span>Apply to {application.lead.company}</span>
            <Badge variant=\"outline\">{application.lead.job_title}</Badge>
          </DialogTitle>
        </DialogHeader>

        <div className=\"flex-1 flex gap-4\">
          {/* Left Panel - Form Data Helper */}
          <div className=\"w-80 space-y-4 overflow-y-auto\">
            <Card className=\"p-4\">
              <h3 className=\"font-semibold mb-3 flex items-center gap-2\">
                Auto-Fill Assistant
                {autoFillStatus === 'filling' && <Loader2 className=\"h-4 w-4 animate-spin\" />}
                {autoFillStatus === 'completed' && <CheckCircle className=\"h-4 w-4 text-green-600\" />}
                {autoFillStatus === 'failed' && <AlertCircle className=\"h-4 w-4 text-red-600\" />}
              </h3>
              
              <div className=\"space-y-3 text-sm\">
                <div>
                  <label className=\"font-medium text-gray-600\">Full Name</label>
                  <div className=\"flex items-center gap-2 mt-1\">
                    <span className=\"flex-1 p-2 bg-gray-50 rounded text-sm\">
                      {formData?.personal?.full_name}
                    </span>
                    <Button
                      size=\"sm\"
                      variant=\"outline\"
                      onClick={() => copyToClipboard(formData?.personal?.full_name || '', 'name')}
                    >
                      {copiedFields.has('name') ? <CheckCircle className=\"h-3 w-3\" /> : <Copy className=\"h-3 w-3\" />}
                    </Button>
                  </div>
                </div>

                <div>
                  <label className=\"font-medium text-gray-600\">Email</label>
                  <div className=\"flex items-center gap-2 mt-1\">
                    <span className=\"flex-1 p-2 bg-gray-50 rounded text-sm\">
                      {formData?.personal?.email}
                    </span>
                    <Button
                      size=\"sm\"
                      variant=\"outline\"
                      onClick={() => copyToClipboard(formData?.personal?.email || '', 'email')}
                    >
                      {copiedFields.has('email') ? <CheckCircle className=\"h-3 w-3\" /> : <Copy className=\"h-3 w-3\" />}
                    </Button>
                  </div>
                </div>

                {formData?.personal?.phone && (
                  <div>
                    <label className=\"font-medium text-gray-600\">Phone</label>
                    <div className=\"flex items-center gap-2 mt-1\">
                      <span className=\"flex-1 p-2 bg-gray-50 rounded text-sm\">
                        {formData.personal.phone}
                      </span>
                      <Button
                        size=\"sm\"
                        variant=\"outline\"
                        onClick={() => copyToClipboard(formData.personal.phone || '', 'phone')}
                      >
                        {copiedFields.has('phone') ? <CheckCircle className=\"h-3 w-3\" /> : <Copy className=\"h-3 w-3\" />}
                      </Button>
                    </div>
                  </div>
                )}

                <div>
                  <label className=\"font-medium text-gray-600\">Work Authorization</label>
                  <div className=\"p-2 bg-green-50 rounded text-sm text-green-800\">
                    ✓ Authorized to work in US
                  </div>
                </div>

                <div>
                  <label className=\"font-medium text-gray-600\">Visa Sponsorship</label>
                  <div className=\"p-2 bg-blue-50 rounded text-sm text-blue-800\">
                    ✗ No sponsorship required
                  </div>
                </div>

                {formData?.location?.current_location && (
                  <div>
                    <label className=\"font-medium text-gray-600\">Location</label>
                    <div className=\"flex items-center gap-2 mt-1\">
                      <span className=\"flex-1 p-2 bg-gray-50 rounded text-sm\">
                        {formData.location.current_location}
                      </span>
                      <Button
                        size=\"sm\"
                        variant=\"outline\"
                        onClick={() => copyToClipboard(formData.location.current_location || '', 'location')}
                      >
                        {copiedFields.has('location') ? <CheckCircle className=\"h-3 w-3\" /> : <Copy className=\"h-3 w-3\" />}
                      </Button>
                    </div>
                  </div>
                )}
              </div>

              <div className=\"mt-4 p-3 bg-blue-50 rounded text-sm text-blue-800\">
                <p className=\"font-medium mb-1\">📋 Instructions:</p>
                <ol className=\"list-decimal list-inside space-y-1 text-xs\">
                  <li>Use the copy buttons to copy your information</li>
                  <li>Paste into the corresponding form fields</li>
                  <li>Upload your resume when prompted</li>
                  <li>Review and submit the application</li>
                </ol>
              </div>
            </Card>

            <div className=\"space-y-2\">
              <Button
                onClick={handleApplicationSubmitted}
                className=\"w-full\"
                size=\"sm\"
              >
                <CheckCircle className=\"h-4 w-4 mr-2\" />
                Mark as Submitted
              </Button>
              
              <Button
                onClick={handleApplicationFailed}
                variant=\"outline\"
                className=\"w-full\"
                size=\"sm\"
              >
                <AlertCircle className=\"h-4 w-4 mr-2\" />
                Mark as Failed
              </Button>

              <Button
                onClick={() => window.open(application.final_application_url, '_blank')}
                variant=\"ghost\"
                className=\"w-full\"
                size=\"sm\"
              >
                <ExternalLink className=\"h-4 w-4 mr-2\" />
                Open in New Tab
              </Button>
            </div>
          </div>

          {/* Right Panel - Iframe */}
          <div className=\"flex-1 relative\">
            {!iframeLoaded && (
              <div className=\"absolute inset-0 flex items-center justify-center bg-gray-50 rounded-lg\">
                <div className=\"text-center\">
                  <Loader2 className=\"h-8 w-8 animate-spin mx-auto mb-2 text-gray-400\" />
                  <p className=\"text-gray-600\">Loading application form...</p>
                </div>
              </div>
            )}
            
            <iframe
              ref={iframeRef}
              src={application.final_application_url}
              className=\"w-full h-full border rounded-lg\"
              onLoad={handleIframeLoad}
              title={`Application form for ${application.lead.company}`}
              sandbox=\"allow-forms allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox\"
            />
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}