import { useState, useEffect } from "react";
import { MapPin, DollarSign, Clock, Building2, Loader2, Zap } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { JobApplicationModal } from "./JobApplicationModal";

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


export function JobQueue() {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isRelevantJobs, setIsRelevantJobs] = useState(false);
  
  // Application processing state
  const [isProcessing, setIsProcessing] = useState(false);
  
  // Modal state
  const [modalOpen, setModalOpen] = useState(false);
  const [currentJobIndex, setCurrentJobIndex] = useState(0);
  const [completedApplications, setCompletedApplications] = useState<number[]>([]);
  const [userFormData, setUserFormData] = useState<any>(null);

  useEffect(() => {
    fetchLeads();
    fetchUserFormData();
  }, []);

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



  const handleApplicationMethod = async () => {
    try {
      // Check if there are jobs to apply to
      if (displayLeads.length === 0) {
        alert('No jobs available to apply to. Please wait for new jobs to load.');
        return;
      }
      
      setIsProcessing(true);
      setCurrentJobIndex(0);
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
    const currentLead = displayLeads[currentJobIndex];
    setCompletedApplications(prev => [...prev, currentLead.id]);
    
    // Move to next job or finish
    if (currentJobIndex < displayLeads.length - 1) {
      setCurrentJobIndex(prev => prev + 1);
      // Modal stays open for the next job
    } else {
      // All jobs completed
      setModalOpen(false);
      setIsProcessing(false);
      console.log(`Completed applications for ${completedApplications.length + 1} jobs`);
      
      // Refresh job queue after completion
      setTimeout(() => {
        refreshJobQueue();
      }, 1000);
    }
  };

  const handleModalClose = () => {
    setModalOpen(false);
    setIsProcessing(false);
  };

  const handleSkipJob = () => {
    console.log(`Skipping job: ${displayLeads[currentJobIndex].job_title} at ${displayLeads[currentJobIndex].company}`);
    
    // Move to next job without marking current as completed
    if (currentJobIndex < displayLeads.length - 1) {
      setCurrentJobIndex(prev => prev + 1);
      // Modal stays open for the next job
    } else {
      // No more jobs to skip to
      setModalOpen(false);
      setIsProcessing(false);
      console.log(`Skipped final job. Completed applications for ${completedApplications.length} jobs`);
      
      // Refresh job queue after completion
      setTimeout(() => {
        refreshJobQueue();
      }, 1000);
    }
  };


  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-semibold text-[#1A1A1A]">Job Opportunities</h2>
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
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-semibold text-[#1A1A1A]">Job Opportunities</h2>
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

  // Limit to first 10 jobs for display
  const displayLeads = leads.slice(0, 10);


  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <h2 className="text-2xl font-semibold text-[#1A1A1A]">Job Opportunities</h2>
          {isRelevantJobs && (
            <Badge variant="default" className="bg-green-600 hover:bg-green-700 text-white">
              AI Matched
            </Badge>
          )}
        </div>
        <Badge variant="secondary" className="px-3 py-1">
          {displayLeads.length} of {leads.length} jobs
        </Badge>
      </div>

      {/* Application Status */}
      {isProcessing && (
        <div className="space-y-2">
          <div className="flex justify-between items-center text-sm">
            <span>Applications in Progress ({completedApplications.length + 1}/{displayLeads.length})</span>
            <span>{Math.round(((completedApplications.length + 1) / displayLeads.length) * 100)}%</span>
          </div>
          {displayLeads[currentJobIndex] && (
            <div className="text-xs text-gray-400 flex items-center gap-2">
              <Loader2 className="h-3 w-3 animate-spin" />
              Currently applying to: {displayLeads[currentJobIndex].job_title} at {displayLeads[currentJobIndex].company}
            </div>
          )}
        </div>
      )}

      {/* Job Cards */}
      <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
        {displayLeads.map((lead) => (
          <Card key={lead.id} className="p-6 hover:shadow-md transition-all duration-300 cursor-pointer relative">
            <div className="space-y-4">
              {/* Header */}
              <div className="space-y-2">
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-2 flex-1">
                    <h3 className="font-semibold text-lg job-card-text leading-tight flex-1">
                      {lead.job_title}
                    </h3>
                  </div>
                  <Badge variant="outline" className="ml-2 flex-shrink-0">
                    {lead.experience_level}
                  </Badge>
                </div>
                <div className="flex items-center gap-2 job-card-text">
                  <Building2 className="h-4 w-4" />
                  <span className="font-medium job-card-text">{lead.company}</span>
                </div>
              </div>

              {/* Details */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                <div className="flex items-center gap-2 job-card-text">
                  <DollarSign className="h-4 w-4 text-green-600" />
                  <span className="font-medium job-card-text">{lead.pay_range}</span>
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

      {/* Start Applying Button */}
      <div className="pt-4 border-t border-border space-y-3">
        <Button 
          onClick={handleApplicationMethod}
          disabled={isProcessing || displayLeads.length === 0}
          className="w-full h-14 bg-primary hover:bg-primary/90 text-primary-foreground text-lg font-medium shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02] disabled:opacity-50"
          size="lg"
        >
          {isProcessing ? (
            <>
              <Loader2 className="mr-3 h-6 w-6 animate-spin" />
              Processing Applications...
            </>
          ) : displayLeads.length === 0 ? (
            <>
              <Loader2 className="mr-3 h-6 w-6 animate-spin" />
              Loading New Jobs...
            </>
          ) : (
            <>
              <Zap className="mr-3 h-6 w-6" />
              Start Applying to {displayLeads.length} Jobs
            </>
          )}
        </Button>
      </div>

      {/* Application Modal */}
      {modalOpen && displayLeads[currentJobIndex] && (
        <JobApplicationModal
          isOpen={modalOpen}
          onClose={handleModalClose}
          jobTitle={displayLeads[currentJobIndex].job_title}
          company={displayLeads[currentJobIndex].company}
          applicationUrl={displayLeads[currentJobIndex].source_url}
          onApplicationComplete={handleApplicationComplete}
          onSkipJob={handleSkipJob}
          userFormData={userFormData}
          autoFillData={displayLeads[currentJobIndex].auto_fill_data}
          currentJobIndex={currentJobIndex}
          totalJobs={displayLeads.length}
        />
      )}
    </div>
  );
}