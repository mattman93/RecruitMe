import { useState, useEffect, useMemo } from "react";
import { createPortal } from "react-dom";
import { MapPin, DollarSign, Clock, Building2, Loader2, Zap, Mail, CheckCircle2, FileText } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Switch } from "./ui/switch";
import { Label } from "./ui/label";
import { JobApplicationModal } from "./JobApplicationModal";
import { ViewJobDescription } from "./ViewJobDescription";

interface JobQueueProps {
  userEmail?: string;
  hasGmailOAuth?: boolean;
}

interface Lead {
  id: number;
  job_title: string;
  company: string;
  pay_range: string;
  description: string;
  location: string;
  employment_type: string;
  experience_level: string;
  source_url: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  auto_fill_data?: {
    has_structure: boolean;
    platform_name: string;
    automation_strategy: string;
    field_mappings: Record<string, string>;
    form_fields: Record<string, any>;
    button_selectors: Record<string, any>;
    success_indicators: Record<string, any>;
    has_captcha: boolean;
    success_rate: number | null;
  };
}


export function JobQueue({ userEmail, hasGmailOAuth }: JobQueueProps) {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isRelevantJobs, setIsRelevantJobs] = useState(false);
  const [sendAsUser, setSendAsUser] = useState(true);
  const [totalPotentialMatches, setTotalPotentialMatches] = useState<number>(0);
  const [flowRank, setFlowRank] = useState<number>(0);
  const [hasResume, setHasResume] = useState<boolean>(false);
  const [hasActiveFilters, setHasActiveFilters] = useState<boolean>(false);

  // Application processing state
  const [isProcessing, setIsProcessing] = useState(false);

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [completedApplications, setCompletedApplications] = useState<number[]>([]);
  const [userFormData, setUserFormData] = useState<any>(null);

  // Animation state
  const [removingJobId, setRemovingJobId] = useState<number | null>(null);
  const [activeLeads, setActiveLeads] = useState<Lead[]>([]);
  const [animationStage, setAnimationStage] = useState<'idle' | 'border-animating' | 'overlay-showing' | 'success-showing'>('idle');

  // View job description modal state
  const [viewJobModalOpen, setViewJobModalOpen] = useState(false);
  const [selectedJob, setSelectedJob] = useState<Lead | null>(null);

  useEffect(() => {
    checkResumeStatus();
    fetchLeads();
    fetchUserFormData();
    fetchFlowRank();
    fetchUserSettings();
  }, []);

  // Update activeLeads when leads change
  useEffect(() => {
    setActiveLeads(leads.slice(0, 10));
  }, [leads]);

  const checkResumeStatus = async () => {
    try {
      const response = await fetch('/api/user/resume', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        setHasResume(!!data.resume);
      }
    } catch (error) {
      console.error('Error checking resume status:', error);
      setHasResume(false);
    }
  };

  const fetchLeads = async () => {
    try {
      setIsLoading(true);
      setError(null);
      
      // Try to fetch relevant/matched jobs first
      let response = await fetch('/api/leads/relevant', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        // Fallback to all leads if relevant endpoint fails
        response = await fetch('/api/leads', {
          credentials: 'include',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
        });
      }

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      const leadsData = result.data || [];

      setLeads(leadsData);
      setTotalPotentialMatches(result.total_potential_matches || leadsData.length);
      setIsRelevantJobs(!!result.matching_strategy && result.matching_strategy !== 'fallback');
      
      if (leadsData.length === 0) {
        setError('No job opportunities available at the moment');
      }
    } catch (error) {
      console.error('Error fetching leads:', error);
      setError('Failed to load job opportunities');
      setLeads([]);
    } finally {
      setIsLoading(false);
    }
  };

  const fetchUserFormData = async () => {
    try {
      const response = await fetch('/api/user/application-form-data', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        if (data.success) {
          setUserFormData(data.formData);
        }
      }
    } catch (error) {
      console.error('Error fetching user form data:', error);
    }
  };

  const fetchFlowRank = async () => {
    try {
      const response = await fetch('/api/user/flow-rank', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        setFlowRank(data.flow_rank || 0);
      }
    } catch (error) {
      console.error('Error fetching FlowRank:', error);
    }
  };

  const fetchUserSettings = async () => {
    try {
      const response = await fetch('/api/user/settings', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();

        // Check if any filters are explicitly set
        const hasFilters = !!(
          data?.min_salary ||
          data?.max_salary ||
          data?.preferred_location ||
          data?.preferred_job_title ||
          (data?.employment_types && Object.values(data.employment_types).some((v: any) => v === true)) ||
          (data?.work_arrangement && Object.values(data.work_arrangement).some((v: any) => v === true))
        );

        setHasActiveFilters(hasFilters);
      }
    } catch (error) {
      console.error('Error fetching user settings:', error);
    }
  };

  const decodeHtmlEntities = (text: string): string => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    return doc.documentElement.textContent || '';
  };

  const truncateDescription = (description: string, maxLength: number = 120) => {
    // Decode HTML entities and strip HTML tags
    const cleaned = decodeHtmlEntities(description).trim();

    if (cleaned.length <= maxLength) return cleaned;
    return cleaned.substring(0, maxLength) + '...';
  };

  const motivationalMessage = useMemo(() => {
    const messages = [
      "Launching your next big career move...",
      "Setting up your next dream job...",
      "Crafting the perfect application...",
      "Opening doors to new opportunities...",
      "Preparing your professional breakthrough..."
    ];
    return messages[Math.floor(Math.random() * messages.length)];
  }, [animationStage]);

  const getExperienceLevelBadgeStyle = (level: string) => {
    const normalizedLevel = level.toLowerCase();

    if (normalizedLevel.includes('entry') || normalizedLevel.includes('junior')) {
      return { backgroundColor: '#dcfce7', color: '#15803d', borderColor: '#86efac' };
    } else if (normalizedLevel.includes('mid') || normalizedLevel.includes('intermediate')) {
      return { backgroundColor: '#dbeafe', color: '#1d4ed8', borderColor: '#93c5fd' };
    } else if (normalizedLevel.includes('senior')) {
      return { backgroundColor: '#f3e8ff', color: '#7e22ce', borderColor: '#d8b4fe' };
    } else if (normalizedLevel.includes('lead') || normalizedLevel.includes('principal') || normalizedLevel.includes('staff')) {
      return { backgroundColor: '#ffedd5', color: '#c2410c', borderColor: '#fed7aa' };
    } else if (normalizedLevel.includes('executive') || normalizedLevel.includes('director') || normalizedLevel.includes('vp')) {
      return { backgroundColor: '#fee2e2', color: '#b91c1c', borderColor: '#fecaca' };
    }

    // Default
    return { backgroundColor: '#f3f4f6', color: '#374151', borderColor: '#d1d5db' };
  };

  const submitApplicationViaAPI = async (lead: Lead) => {
    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      // Submit application via email API
      const response = await fetch('/api/automation/process-email-application', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
        credentials: 'include',
        body: JSON.stringify({
          job_url: lead.source_url,
        }),
      });

      const result = await response.json();
      return result;
    } catch (error) {
      console.error('Error submitting application:', error);
      throw error;
    }
  };

  const pollJobStatus = async (sessionKey: string) => {
    let pollCount = 0;
    const maxPolls = 150; // 5 minutes at 2-second intervals

    const pollInterval = setInterval(async () => {
      pollCount++;

      try{
        const response = await fetch(`/api/automation/status/${sessionKey}`, {
          credentials: 'include',
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const status = await response.json();

        // Check if completed
        if (status.status === 'submitted' || status.status === 'success' || status.status === 'completed') {
          clearInterval(pollInterval);

          // Show success overlay
          setAnimationStage('success-showing');
          setTimeout(() => {
            handleAutoApplicationComplete();
          }, 2000);
        } else if (status.status === 'needs_user_input') {
          clearInterval(pollInterval);
          setAnimationStage('idle');
          setModalOpen(true);
        } else if (status.status === 'error' || status.status === 'failed') {
          clearInterval(pollInterval);
          setAnimationStage('idle');
          setModalOpen(true);
        } else if (pollCount >= maxPolls) {
          clearInterval(pollInterval);
          setAnimationStage('idle');
          setModalOpen(true);
        }
        // Otherwise keep polling (status is still 'queued' or 'processing')
      } catch (error) {
        console.error('[Polling] Error:', error);
        clearInterval(pollInterval);
        setAnimationStage('idle');
        setModalOpen(true);
      }
    }, 2000); // Poll every 2 seconds
  };

  const handleApplicationMethod = async () => {
    try {
      // If queue is empty, load more jobs
      if (activeLeads.length === 0) {
        await fetchLeads();
        return;
      }

      // Check if there are jobs to apply to
      if (activeLeads.length === 0) {
        alert('No jobs available to apply to. Please wait for new jobs to load.');
        return;
      }

      setIsProcessing(true);
      setCompletedApplications([]);

      const currentLead = activeLeads[0];

      // Start animation sequence
      // Stage 1: Border animation
      setAnimationStage('border-animating');

      // Stage 2: Show loading overlay after border animation
      setTimeout(async () => {
        setAnimationStage('overlay-showing');

        // Stage 3: Always try to auto-submit via API
        // The API will tell us if it needs user input
        try {
          const result = await submitApplicationViaAPI(currentLead);

          // Check for validation errors
          if (result.errors || !result.status) {
            console.error('[Animation] API validation error:', result);
            setAnimationStage('idle');
            setModalOpen(true);
            return;
          }

          if (result.status === 'submitted' || result.status === 'success') {
            // Show success overlay
            setAnimationStage('success-showing');

            // After showing success, remove the card
            setTimeout(() => {
              handleAutoApplicationComplete();
            }, 2000); // Show success for 2 seconds
          } else if (result.status === 'processing') {
            // Job is being processed asynchronously - start polling
            const sessionKey = (result as any).session_key;
            if (sessionKey) {
              pollJobStatus(sessionKey);
            } else {
              console.error('[Animation] No session_key in processing response');
              setAnimationStage('idle');
              setModalOpen(true);
            }
          } else if (result.status === 'needs_user_input') {
            // Needs user input - open modal
            setAnimationStage('idle');
            setModalOpen(true);
          } else {
            // Error or unknown status - open modal for manual intervention
            setAnimationStage('idle');
            setModalOpen(true);
          }
        } catch (error) {
          console.error('[Animation] Error during API call:', error);
          // Open modal on error
          setAnimationStage('idle');
          setModalOpen(true);
        }
      }, 2000);

    } catch (error) {
      console.error('Error starting applications:', error);
      setIsProcessing(false);
      setAnimationStage('idle');
      alert('Failed to start applications. Please try again.');
    }
  };

  const handleAutoApplicationComplete = () => {
    const currentLead = activeLeads[0];
    setCompletedApplications(prev => [...prev, currentLead.id]);

    // Refresh FlowRank after application
    fetchFlowRank();

    // Start exit animation
    setRemovingJobId(currentLead.id);
    setAnimationStage('idle');
    setIsProcessing(false);

    // After animation completes, remove from activeLeads
    setTimeout(() => {
      setActiveLeads(prev => prev.slice(1)); // Remove first job
      setRemovingJobId(null);

      // Check if all jobs are done
      if (activeLeads.length <= 1) {
        // All jobs completed
      }
    }, 500); // Match CSS animation duration
  };

  const handleApplicationComplete = () => {
    const currentLead = activeLeads[0]; // Always working with the first card now
    setCompletedApplications(prev => [...prev, currentLead.id]);

    // Refresh FlowRank after application
    fetchFlowRank();

    // Close modal
    setModalOpen(false);

    // Show success overlay on card
    setAnimationStage('success-showing');

    // After showing success, start exit animation
    setTimeout(() => {
      setRemovingJobId(currentLead.id);
      setAnimationStage('idle');
      setIsProcessing(false);

      // After animation completes, remove from activeLeads
      setTimeout(() => {
        setActiveLeads(prev => prev.slice(1)); // Remove first job
        setRemovingJobId(null);

        // Check if all jobs are done
        if (activeLeads.length <= 1) {
          // All jobs completed
        }
      }, 500); // Match CSS animation duration
    }, 2000); // Show success for 2 seconds
  };

  const handleModalClose = () => {
    setModalOpen(false);
    setIsProcessing(false);
  };

  const handleViewJob = (lead: Lead) => {
    setSelectedJob(lead);
    setViewJobModalOpen(true);
  };

  const handleCloseViewJob = () => {
    setViewJobModalOpen(false);
    setSelectedJob(null);
  };

  const handleSkipJob = () => {
    const currentLead = activeLeads[0];

    // Close modal and stop processing state immediately
    setModalOpen(false);
    setIsProcessing(false);

    // Trigger exit animation for skipped job
    setTimeout(() => {
      setRemovingJobId(currentLead.id);

      setTimeout(() => {
        setActiveLeads(prev => prev.slice(1));
        setRemovingJobId(null);

        // Check if all jobs are done
        if (activeLeads.length <= 1) {
          // All jobs skipped/completed
        }
        // If there are more jobs, the button will be enabled and user can click it
      }, 500);
    }, 100);
  };


  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between pt-8">
          <h3 className="text-2xl font-semibold text-[#1A1A1A]">Job Matches</h3>
          <Badge variant="secondary" className="px-3 py-1">
            Loading...
          </Badge>
        </div>
        <div className="space-y-4">
          {[...Array(5)].map((_, i) => (
            <Card key={i} className="p-6 animate-pulse">
              <div className="space-y-3">
                <div className="h-4 bg-muted rounded w-3/4"></div>
                <div className="h-3 bg-muted rounded w-1/2"></div>
                <div className="h-3 bg-muted rounded w-full"></div>
              </div>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between pt-8">
          <h3 className="text-2xl font-semibold text-[#1A1A1A]">Job Matches</h3>
        </div>
        <Card className="p-6 text-center">
          <p className="job-card-text">{error}</p>
          <Button 
            onClick={fetchLeads} 
            variant="outline" 
            className="mt-4"
          >
            Try Again
          </Button>
        </Card>
      </div>
    );
  }

  // Calculate button text and state
  const getButtonContent = () => {
    if (activeLeads.length === 0) {
      return {
        text: "Load More Matches",
        icon: <Zap className="mr-2 h-4 w-4" />
      };
    }

    const applied = completedApplications.length;
    const totalJobs = Math.min(leads.length, 10);

    if (!isProcessing && applied === 0) {
      return {
        text: `Start Applying to ${activeLeads.length} Jobs`,
        icon: <Zap className="mr-2 h-4 w-4" />
      };
    }

    if (isProcessing) {
      return {
        text: `Apply to Next [${applied + 1}/${totalJobs}]`,
        icon: <Loader2 className="mr-2 h-4 w-4 animate-spin" />
      };
    }

    return {
      text: `Apply to Next [${applied}/${totalJobs}]`,
      icon: <Zap className="mr-2 h-4 w-4" />
    };
  };

  const buttonContent = getButtonContent();

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between pt-8">
        <div className="flex items-center gap-3">
          <h3 className="text-2xl font-semibold text-[#1A1A1A]">Job Matches</h3>
          {isRelevantJobs && (
            <Badge variant="default" className="bg-green-600 hover:bg-green-700 text-white">
              AI Matched
            </Badge>
          )}
        </div>
        <Badge variant="secondary" className="px-3 py-1">
          {activeLeads.length} of {totalPotentialMatches > leads.length ? `${totalPotentialMatches}+` : totalPotentialMatches} jobs
        </Badge>
      </div>

      {/* No Resume Warning */}
      {!hasResume && (
        <Card className="p-8 text-center bg-gradient-to-br from-blue-50 to-purple-50 border-2 border-dashed border-primary/30">
          <FileText className="h-16 w-16 text-primary mx-auto mb-4" />
          <h3 className="text-xl font-semibold text-foreground mb-2">Upload Your Resume to Begin</h3>
          <p className="text-muted-foreground mb-4">
            Upload your resume to start seeing personalized job matches tailored to your experience and skills.
          </p>
        </Card>
      )}

      {/* FlowRank Badge (left), Email Toggle (center), Apply Button (right) */}
      {hasResume && (
        <>
          <div className="flex items-center gap-4">
            {/* FlowRank Badge */}
            <div className="flowrank-badge">
              <div className="flex items-center gap-2">
                <div className="flowrank-dot"></div>
                <span className="flowrank-text">FlowRank</span>
              </div>
              <span className="text-muted-foreground text-sm">:</span>
              <span className="flowrank-number">{flowRank}</span>
            </div>

            {/* Filtered with Preferences Badge */}
            {hasActiveFilters && (
              <div className="filtered-badge">
                <div className="flex items-center gap-2">
                  <div className="filtered-dot"></div>
                  <span className="filtered-text">Filtered with Preferences</span>
                </div>
              </div>
            )}

            {/* Center spacer */}
            <div className="flex-1 flex justify-center">
              {userEmail && hasGmailOAuth && (
                <div className="flex items-center gap-3 px-4 py-2 bg-white/60 backdrop-blur-sm rounded-lg border border-gray-200">
                  <Mail className="h-4 w-4 text-gray-600" />
                  <Label htmlFor="send-as-user" className="text-sm font-medium text-gray-700 cursor-pointer">
                    Applying from {userEmail}
                  </Label>
                  <Switch
                    id="send-as-user"
                    checked={sendAsUser}
                    onCheckedChange={setSendAsUser}
                  />
                </div>
              )}
            </div>

            {/* Apply Button */}
            <Button
              onClick={handleApplicationMethod}
              disabled={isProcessing}
              className="bg-primary hover:bg-primary/90 text-primary-foreground shadow-md"
              size="default"
            >
              {buttonContent.icon}
              {buttonContent.text}
            </Button>
          </div>

          {/* Job Cards */}
          <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
        {activeLeads.map((lead, index) => {
          const isFirstCard = index === 0;

          // Base classes
          let cardClasses = "p-6 hover:shadow-md transition-all duration-500 cursor-pointer relative overflow-hidden";

          // Animation classes for card removal
          if (removingJobId === lead.id) {
            cardClasses += " animate-slide-out-up opacity-0 -translate-y-4";
          } else {
            cardClasses += " animate-slide-in-down";
          }

          // Add animate-pulse for border animation
          if (isFirstCard && animationStage === 'border-animating') {
            cardClasses += " animate-pulse";
          }

          // Inline style for border (bypasses Tailwind class conflicts)
          const cardStyle = isFirstCard && animationStage === 'border-animating'
            ? {
                border: '2px solid #3b82f6',
                borderColor: '#3b82f6',
                borderWidth: '2px',
                borderStyle: 'solid'
              }
            : undefined;


          return (
          <Card
            key={lead.id}
            className={`${cardClasses} cursor-pointer hover:shadow-lg transition-shadow`}
            style={{
              ...cardStyle,
              position: 'relative' // Ensure relative positioning for absolute overlays
            }}
            onClick={() => handleViewJob(lead)}
          >
            <div className="space-y-4" style={{ position: 'relative', zIndex: 1 }}>
              {/* Header */}
              <div className="space-y-2">
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-2 flex-1">
                    <h3 className="font-semibold text-lg job-card-text leading-tight flex-1">
                      {lead.job_title}
                    </h3>
                  </div>
                  <span
                    className="ml-2 flex-shrink-0 inline-flex items-center justify-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
                    style={getExperienceLevelBadgeStyle(lead.experience_level)}
                  >
                    {lead.experience_level}
                  </span>
                </div>
                <div className="flex items-center gap-2 job-card-text">
                  <Building2 className="h-4 w-4" />
                  <span className="font-medium job-card-text">{lead.company}</span>
                </div>
              </div>

              {/* Details */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                <div className="flex items-center gap-2">
                  <DollarSign className="h-4 w-4 text-primary" />
                  <span className="font-medium text-primary">{lead.pay_range}</span>
                </div>
                <div className="flex items-center gap-2 job-card-text">
                  <MapPin className="h-4 w-4 text-blue-600" />
                  <span className="job-card-text">{lead.location}</span>
                </div>
                <div className="flex items-center gap-2 job-card-text">
                  <Clock className="h-4 w-4 text-purple-600" />
                  <span className="job-card-text">{lead.employment_type}</span>
                </div>
              </div>

              {/* Description */}
              <p className="text-sm job-card-text leading-relaxed">
                {truncateDescription(lead.description)}
              </p>
            </div>

            {/* Overlay for loading animation stage */}
            {isFirstCard && animationStage === 'overlay-showing' && (
                <div
                  className="absolute flex items-center justify-center rounded-lg"
                  style={{
                    top: 0,
                    left: 0,
                    right: 0,
                    bottom: 0,
                    zIndex: 10,
                    background: 'linear-gradient(to bottom right, rgba(59, 130, 246, 0.9), rgba(147, 51, 234, 0.9))',
                    borderRadius: '0.5rem'
                  }}
                >
                  <div className="text-center text-white p-6">
                    <Loader2 className="w-8 h-8 animate-spin mx-auto mb-4" />
                    <p className="font-medium mb-2">{motivationalMessage}</p>
                    <div className="w-32 bg-white/30 rounded-full h-1.5 mx-auto">
                      <div className="bg-white h-full rounded-full w-3/4"></div>
                    </div>
                  </div>
                </div>
            )}

            {/* Overlay for success animation stage */}
            {isFirstCard && animationStage === 'success-showing' && (
              <div
                className="absolute flex items-center justify-center rounded-lg"
                style={{
                  top: 0,
                  left: 0,
                  right: 0,
                  bottom: 0,
                  zIndex: 10,
                  background: 'linear-gradient(to bottom right, rgba(59, 130, 246, 0.9), rgba(147, 51, 234, 0.9))',
                  borderRadius: '0.5rem'
                }}
              >
                <div className="text-center text-white p-6">
                  <CheckCircle2 className="w-12 h-12 mx-auto mb-4" />
                  <p className="font-semibold text-lg mb-1">Application Successfully Submitted!</p>
                  <p className="text-sm opacity-90">Moving to your next opportunity...</p>
                </div>
              </div>
            )}
          </Card>
          );
        })}
      </div>
      </>
      )}

      {/* Application Modal - Rendered via Portal to bypass transform parent */}
      {modalOpen && activeLeads[0] && createPortal(
        <JobApplicationModal
          isOpen={modalOpen}
          onClose={handleModalClose}
          jobTitle={activeLeads[0].job_title}
          company={activeLeads[0].company}
          applicationUrl={activeLeads[0].source_url}
          onApplicationComplete={handleApplicationComplete}
          onSkipJob={handleSkipJob}
          userFormData={userFormData}
          currentJobIndex={10 - activeLeads.length}
          totalJobs={10}
          skipAutoStart={true}
        />,
        document.body
      )}

      {/* View Job Description Modal - Rendered via Portal */}
      {viewJobModalOpen && selectedJob && createPortal(
        <ViewJobDescription
          isOpen={viewJobModalOpen}
          onClose={handleCloseViewJob}
          jobTitle={selectedJob.job_title}
          company={selectedJob.company}
          salary={selectedJob.pay_range}
          location={selectedJob.location}
          description={selectedJob.description}
          jobUrl={selectedJob.source_url}
        />,
        document.body
      )}
    </div>
  );
}