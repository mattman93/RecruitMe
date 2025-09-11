import { useState, useEffect } from "react";
import { MapPin, DollarSign, Clock, Building2, Play } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";

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

interface JobQueueProps {
  onStartApplying: () => void;
}

export function JobQueue({ onStartApplying }: JobQueueProps) {
  const [leads, setLeads] = useState<Lead[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchLeads();
  }, []);

  const fetchLeads = async () => {
    try {
      const response = await fetch('/api/leads', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch leads');
      }

      const data = await response.json();
      setLeads(data.data || []);
    } catch (error) {
      console.error('Error fetching leads:', error);
      setError('Failed to load job opportunities');
    } finally {
      setIsLoading(false);
    }
  };

  const truncateDescription = (description: string, maxLength: number = 120) => {
    if (description.length <= maxLength) return description;
    return description.substring(0, maxLength) + '...';
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

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <h2 className="text-2xl font-semibold text-white">Job Opportunities</h2>
        <Badge variant="secondary" className="px-3 py-1">
          {leads.length} jobs found
        </Badge>
      </div>

      {/* Job Cards */}
      <div className="space-y-4 max-h-96 overflow-y-auto pr-2">
        {leads.map((lead) => (
          <Card key={lead.id} className="p-6 hover:shadow-md transition-shadow cursor-pointer">
            <div className="space-y-4">
              {/* Header */}
              <div className="space-y-2">
                <div className="flex items-start justify-between">
                  <h3 className="font-semibold text-lg job-card-text leading-tight">
                    {lead.job_title}
                  </h3>
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
      <div className="pt-4 border-t border-border">
        <Button 
          onClick={onStartApplying}
          className="w-full h-14 bg-primary hover:bg-primary/90 text-primary-foreground text-lg font-medium shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]"
          size="lg"
        >
          <Play className="mr-3 h-6 w-6" />
          Start Applying to {leads.length} Jobs
        </Button>
      </div>
    </div>
  );
}