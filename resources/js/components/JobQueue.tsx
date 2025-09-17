import { useState, useEffect } from "react";
import { MapPin, DollarSign, Clock, Building2, CheckCircle, AlertCircle, Loader2, Zap } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Progress } from "./ui/progress";

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
}

interface BatchStatus {
  batch_id: string;
  status: 'queued' | 'processing' | 'completed' | 'failed';
  total: number;
  completed: number;
  successful: number;
  failed: number;
  current_application?: {
    id: number;
    job_title: string;
    company: string;
    method: string;
  };
}

export function JobQueue() {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isRelevantJobs, setIsRelevantJobs] = useState(false);
  
  // Application processing state
  const [isProcessing, setIsProcessing] = useState(false);
  const [batchStatus, setBatchStatus] = useState<BatchStatus | null>(null);
  const [applicationStatuses, setApplicationStatuses] = useState<Record<number, 'pending' | 'processing' | 'success' | 'failed'>>({});

  useEffect(() => {
    fetchLeads();
  }, []);

  const fetchLeads = async () => {
    try {
      // First try to get relevant jobs based on user's resume
      let response = await fetch('/api/leads/relevant?limit=50', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      // If relevant jobs fail or return empty, fallback to all jobs
      if (!response.ok || response.status === 401) {
        response = await fetch('/api/leads?limit=50', {
          credentials: 'include',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
          },
        });
      }

      if (!response.ok) {
        throw new Error('Failed to fetch leads');
      }

      const data = await response.json();
      setLeads(data.data || []);
      
      // Check if we got relevant jobs (has matching_strategy in response)
      setIsRelevantJobs(!!data.matching_strategy);
    } catch (error) {
      console.error('Error fetching leads:', error);
      setError('Failed to load job opportunities');
    } finally {
      setIsLoading(false);
    }
  };

  const refreshJobQueue = async () => {
    console.log('Refreshing job queue after successful applications...');
    
    // Clear current statuses
    setApplicationStatuses({});
    setBatchStatus(null);
    
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

  const testAuth = async () => {
    try {
      const response = await fetch('/api/test-auth', {
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
        }
      });
      const data = await response.json();
      console.log('Auth test:', data);
      alert(`Auth test: ${JSON.stringify(data)}`);
    } catch (error) {
      console.error('Auth test error:', error);
    }
  };

  const getCsrfToken = () => {
    // For Sanctum, we need to read the XSRF-TOKEN cookie, not the meta tag
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
      const [name, value] = cookie.trim().split('=');
      if (name === 'XSRF-TOKEN') {
        return decodeURIComponent(value);
      }
    }
    
    // Fallback to meta tag if cookie not found
    const metaTag = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement;
    return metaTag?.getAttribute('content') || '';
  };

  const handleApplicationMethod = async (method: 'semi_auto' | 'iframe' | 'full_auto') => {
    try {
      // Check if there are jobs to apply to
      if (displayLeads.length === 0) {
        alert('No jobs available to apply to. Please wait for new jobs to load.');
        return;
      }
      
      setIsProcessing(true);
      
      // Initialize all leads as pending
      const leadIds = displayLeads.map(lead => lead.id);
      const initialStatuses: Record<number, 'pending' | 'processing' | 'success' | 'failed'> = {};
      leadIds.forEach(id => { initialStatuses[id] = 'pending'; });
      setApplicationStatuses(initialStatuses);
      
      // First, get a fresh CSRF cookie
      await fetch('/sanctum/csrf-cookie', {
        credentials: 'include'
      });
      
      // Queue applications with selected method
      const response = await fetch('/api/applications/queue', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'X-XSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'include',
        body: JSON.stringify({
          lead_ids: leadIds,
          application_method: method
        })
      });

      if (!response.ok) {
        const errorData = await response.text();
        console.error('API Error:', response.status, errorData);
        throw new Error(`Failed to queue applications: ${response.status} ${errorData}`);
      }

      const data = await response.json();
      console.log('Applications queued:', data);
      
      if (data.batch_id && data.access_token) {
        // Start polling for batch status with access token
        pollBatchStatus(data.batch_id, data.access_token);
      } else {
        setIsProcessing(false);
        alert('Applications queued but no batch ID returned');
      }
      
    } catch (error) {
      console.error('Error queueing applications:', error);
      setIsProcessing(false);
      alert('Failed to queue applications. Please try again.');
    }
  };

  const pollBatchStatus = async (batchId: string, accessToken?: string) => {
    const poll = async () => {
      try {
        // Use token-based authentication if available, otherwise fall back to session
        const url = accessToken 
          ? `/api/applications/batch-status/${batchId}?token=${accessToken}`
          : `/api/applications/batch-status/${batchId}`;

        const headers: Record<string, string> = {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        };

        // Only add CSRF token for session-based requests
        if (!accessToken) {
          // Refresh CSRF cookie before each polling request to prevent expiration
          await fetch('/sanctum/csrf-cookie', {
            credentials: 'include'
          });
          headers['X-XSRF-TOKEN'] = getCsrfToken();
        }

        console.log('Polling batch status:', {
          url,
          accessToken: accessToken ? 'present' : 'none',
          headers
        });

        const response = await fetch(url, {
          credentials: accessToken ? 'omit' : 'include', // No cookies needed for token-based requests
          headers
        });

        console.log('Batch status response:', {
          status: response.status,
          ok: response.ok,
          url: response.url
        });

        if (!response.ok) {
          const errorText = await response.text();
          console.error('Batch status error:', {
            status: response.status,
            statusText: response.statusText,
            body: errorText,
            url
          });
          throw new Error(`Failed to get batch status: ${response.status} ${response.statusText}`);
        }

        const status: BatchStatus = await response.json();
        console.log('Batch status received:', status);
        setBatchStatus(status);

        // Update individual application statuses based on batch status
        if (status.status === 'queued') {
          // When queued, show all as pending (yellow)
          const queuedStatuses: Record<number, 'pending' | 'processing' | 'success' | 'failed'> = {};
          displayLeads.forEach(lead => {
            queuedStatuses[lead.id] = 'pending';
          });
          setApplicationStatuses(queuedStatuses);
        } else if (status.current_application) {
          // When processing, show current application as processing (blue)
          setApplicationStatuses(prev => ({
            ...prev,
            [status.current_application!.id]: 'processing'
          }));
        }

        // If batch is still processing, continue polling
        if (status.status === 'queued' || status.status === 'processing') {
          setTimeout(poll, 2000); // Poll every 2 seconds
        } else {
          // Batch completed
          setIsProcessing(false);
          
          // Update final statuses (this is simplified - in reality you'd need to map specific results)
          const finalStatuses: Record<number, 'pending' | 'processing' | 'success' | 'failed'> = {};
          displayLeads.forEach((lead, index) => {
            if (index < status.successful) {
              finalStatuses[lead.id] = 'success';
            } else {
              finalStatuses[lead.id] = 'failed';
            }
          });
          setApplicationStatuses(finalStatuses);
          
          // After showing final results for 3 seconds, refresh the job queue
          setTimeout(() => {
            refreshJobQueue();
          }, 3000);
        }
      } catch (error) {
        console.error('Error polling batch status:', error);
        setIsProcessing(false);
      }
    };

    poll();
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-between">
          <h2 className="text-2xl font-semibold text-white">Job Opportunities</h2>
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
          <h2 className="text-2xl font-semibold text-white">Job Opportunities</h2>
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

  const getCardStatusStyle = (leadId: number) => {
    const status = applicationStatuses[leadId];
    switch (status) {
      case 'processing':
        return 'ring-2 ring-blue-400 bg-blue-900/20 border-blue-400/30';
      case 'success':
        return 'ring-2 ring-green-400 bg-green-900/20 border-green-400/30';
      case 'failed':
        return 'ring-2 ring-red-400 bg-red-900/20 border-red-400/30';
      case 'pending':
        return isProcessing ? 'ring-2 ring-yellow-400 bg-yellow-900/20 border-yellow-400/30' : '';
      default:
        return '';
    }
  };

  const getStatusIcon = (leadId: number) => {
    const status = applicationStatuses[leadId];
    switch (status) {
      case 'processing':
        return <Loader2 className="h-5 w-5 text-blue-400 animate-spin" />;
      case 'success':
        return <CheckCircle className="h-5 w-5 text-green-400" />;
      case 'failed':
        return <AlertCircle className="h-5 w-5 text-red-400" />;
      case 'pending':
        return isProcessing ? <Clock className="h-5 w-5 text-yellow-400" /> : null;
      default:
        return null;
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <h2 className="text-2xl font-semibold text-white">Job Opportunities</h2>
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

      {/* Progress Bar */}
      {isProcessing && batchStatus && (
        <div className="space-y-2">
          <div className="flex justify-between items-center text-sm">
            <span>Processing Applications ({batchStatus.completed}/{batchStatus.total})</span>
            <span>{Math.round((batchStatus.completed / batchStatus.total) * 100)}%</span>
          </div>
          <Progress value={(batchStatus.completed / batchStatus.total) * 100} />
          {batchStatus.current_application && (
            <div className="text-xs text-gray-600 flex items-center gap-2">
              <Loader2 className="h-3 w-3 animate-spin" />
              Currently applying to: {batchStatus.current_application.job_title} at {batchStatus.current_application.company}
            </div>
          )}
        </div>
      )}

      {/* Job Cards */}
      <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
        {displayLeads.map((lead) => (
          <Card key={lead.id} className={`p-6 hover:shadow-md transition-all duration-300 cursor-pointer relative ${getCardStatusStyle(lead.id)}`}>
            <div className="space-y-4">
              {/* Header */}
              <div className="space-y-2">
                <div className="flex items-start justify-between">
                  <div className="flex items-start gap-2 flex-1">
                    <h3 className="font-semibold text-lg job-card-text leading-tight flex-1">
                      {lead.job_title}
                    </h3>
                    {getStatusIcon(lead.id)}
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
          onClick={() => handleApplicationMethod('semi_auto')}
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
              Start Applying to {displayLeads.length} Jobs (Semi-Auto)
            </>
          )}
        </Button>
        
        {/* Application Method Selection */}
        <div className="flex gap-2 text-sm">
          <Button 
            variant="outline" 
            size="sm" 
            className="flex-1"
            disabled={isProcessing}
            onClick={testAuth}
          >
            🔍 Test Auth
          </Button>
          <Button 
            variant="outline" 
            size="sm" 
            className="flex-1"
            disabled={isProcessing || displayLeads.length === 0}
            onClick={() => handleApplicationMethod('semi_auto')}
          >
            {isProcessing ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : '🤖'} Semi-Auto
          </Button>
          <Button 
            variant="outline" 
            size="sm" 
            className="flex-1"
            disabled={isProcessing || displayLeads.length === 0}
            onClick={() => handleApplicationMethod('iframe')}
          >
            {isProcessing ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : '🖼️'} Assisted
          </Button>
          <Button 
            variant="outline" 
            size="sm" 
            className="flex-1"
            disabled={isProcessing || displayLeads.length === 0}
            onClick={() => handleApplicationMethod('full_auto')}
          >
            {isProcessing ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : '⚡'} Full Auto
          </Button>
        </div>
      </div>
    </div>
  );
}