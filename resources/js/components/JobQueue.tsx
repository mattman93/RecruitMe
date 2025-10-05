import { useState, useEffect } from "react";
import { MapPin, DollarSign, Clock, Building2, Loader2, Zap, Mail } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Switch } from "./ui/switch";
import { Label } from "./ui/label";
import { JobApplicationModal } from "./JobApplicationModal";

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

  // Application processing state
  const [isProcessing, setIsProcessing] = useState(false);

  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [completedApplications, setCompletedApplications] = useState<number[]>([]);
  const [userFormData, setUserFormData] = useState<any>(null);

  // Animation state
  const [removingJobId, setRemovingJobId] = useState<number | null>(null);
  const [activeLeads, setActiveLeads] = useState<Lead[]>([]);

  useEffect(() => {
    fetchLeads();
    fetchUserFormData();
  }, []);

  // Update activeLeads when leads change
  useEffect(() => {
    setActiveLeads(leads.slice(0, 10));
  }, [leads]);

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
        console.log('Relevant jobs failed, falling back to all leads');
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
      
      console.log(`Loaded ${leadsData.length} job leads`, {
        total: result.total,
        strategy: result.matching_strategy,
        message: result.message
      });

      setLeads(leadsData);
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

  const refreshJobQueue = async () => {
    console.log('Refreshing job queue after successful applications...');
    
    // Show loading state
    setIsLoading(true);
    setError(null);
    
    // Fetch fresh leads directly from all leads endpoint (skip relevant)
    try {
      const response = await fetch('/api/leads?limit=50', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch leads');
      }

      const data = await response.json();
      setLeads(data.data || []);
      setIsRelevantJobs(false); // These are not relevant-matched jobs
      
    } catch (error) {
      console.error('Error refreshing leads:', error);
      setError('Failed to load new job opportunities');
    } finally {
      setIsLoading(false);
    }
    
    console.log('Job queue refreshed with new leads');
  };

  const truncateDescription = (description: string, maxLength: number = 120) => {
    if (description.length <= maxLength) return description;
    return description.substring(0, maxLength) + '...';
  };

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

      // Start with the first job
      setModalOpen(true);

    } catch (error) {
      console.error('Error starting applications:', error);
      setIsProcessing(false);
      alert('Failed to start applications. Please try again.');
    }
  };

  const handleApplicationComplete = () => {
    const currentLead = activeLeads[0]; // Always working with the first card now
    setCompletedApplications(prev => [...prev, currentLead.id]);

    // Close modal and stop processing state immediately
    setModalOpen(false);
    setIsProcessing(false);

    // After 5 second delay (already shown in modal), trigger animation
    setTimeout(() => {
      // Start exit animation
      setRemovingJobId(currentLead.id);

      // After animation completes, remove from activeLeads
      setTimeout(() => {
        setActiveLeads(prev => prev.slice(1)); // Remove first job
        setRemovingJobId(null);

        // Check if all jobs are done
        if (activeLeads.length <= 1) {
          console.log(`Completed applications for ${completedApplications.length + 1} jobs`);
        }
        // If there are more jobs, the button will be enabled and user can click it
      }, 500); // Match CSS animation duration
    }, 100); // Small delay after modal closes
  };

  const handleModalClose = () => {
    setModalOpen(false);
    setIsProcessing(false);
  };

  const handleSkipJob = () => {
    const currentLead = activeLeads[0];
    console.log(`Skipping job: ${currentLead.job_title} at ${currentLead.company}`);

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
          console.log(`Skipped final job. Completed applications for ${completedApplications.length} jobs`);
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

    const totalOriginal = 10;
    const remaining = activeLeads.length;
    const applied = totalOriginal - remaining;

    if (!isProcessing && applied === 0) {
      return {
        text: `Start Applying to ${activeLeads.length} Jobs`,
        icon: <Zap className="mr-2 h-4 w-4" />
      };
    }

    if (isProcessing) {
      return {
        text: `Apply to Next [${applied + 1}/${totalOriginal}]`,
        icon: <Loader2 className="mr-2 h-4 w-4 animate-spin" />
      };
    }

    return {
      text: `Apply to Next [${applied}/${totalOriginal}]`,
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
          {activeLeads.length} of {leads.length} jobs
        </Badge>
      </div>

      {/* FlowRank Badge, Email Toggle, and Apply Button */}
      <div className="flex items-center gap-4" style={{ justifyContent: 'end' }}>
        {/* FlowRank Badge */}
        <div className="flowrank-badge">
          <div className="flex items-center gap-2">
            <div className="flowrank-dot"></div>
            <span className="flowrank-text">FlowRank</span>
          </div>
          <span className="text-muted-foreground text-sm">:</span>
          <span className="flowrank-number">125</span>
        </div>

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
        {activeLeads.map((lead) => (
          <Card
            key={lead.id}
            className={`p-6 hover:shadow-md transition-all duration-500 cursor-pointer relative ${
              removingJobId === lead.id
                ? 'animate-slide-out-up opacity-0 -translate-y-4'
                : 'animate-slide-in-down'
            }`}
          >
            <div className="space-y-4">
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
          </Card>
        ))}
      </div>

      {/* Application Modal */}
      {modalOpen && activeLeads[0] && (
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
        />
      )}
    </div>
  );
}