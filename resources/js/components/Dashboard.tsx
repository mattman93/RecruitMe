import { useState, useEffect } from "react";
import { FileText, Download, Edit3, CheckCircle2, Mail } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Switch } from "./ui/switch";
import { Label } from "./ui/label";
import { JobQueue } from "./JobQueue";
import { Footer } from "./Footer";
import WorkExperience from "./WorkExperience";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

interface UploadedResume {
  id: number;
  original_name: string;
  stored_name: string;
  path: string;
  size: number;
  type: string;
  created_at: string;
}

interface WorkExperienceItem {
  id: number;
  job_title: string;
  company: string;
  location?: string;
  date_range: string;
  description?: string;
  achievements?: string[];
  is_current: boolean;
}

export function Dashboard() {
  const { ref, isVisible } = useScrollAnimation(0.2);
  const [uploadedResume, setUploadedResume] = useState<UploadedResume | null>(null);
  const [workExperience, setWorkExperience] = useState<WorkExperienceItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [sendAsUser, setSendAsUser] = useState(true);
  const [userEmail, setUserEmail] = useState('');
  const [hasGmailOAuth, setHasGmailOAuth] = useState(false);
  const [activeTab, setActiveTab] = useState<'matches' | 'applications' | 'settings'>('matches');

  useEffect(() => {
    fetchUploadedResume();
    fetchWorkExperience();
    fetchUserInfo();
    fetchOAuthStatus();
  }, []);
  

const fetchUploadedResume = async () => {
  try {
    const response = await fetch('/api/user/resume', {
      credentials: 'include', // Include cookies
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    if (response.ok) {
      const data = await response.json();
      if (data.resume) {
        setUploadedResume(data.resume);
      }
      console.log('authenticated');
    } else if (response.status === 401) {
      // User is not authenticated, handle accordingly
      console.log('User not authenticated');
    }
  } catch (error) {
    console.error('Error fetching resume:', error);
  } finally {
    setIsLoading(false);
  }
};

const fetchWorkExperience = async () => {
  try {
    const response = await fetch('/api/user/work-experience', {
      credentials: 'include',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    if (response.ok) {
      const data = await response.json();
      setWorkExperience(data.work_experience || []);
    }
  } catch (error) {
    console.error('Error fetching work experience:', error);
  }
};

const fetchUserInfo = async () => {
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
      setUserEmail(data.formData?.email || '');
    }
  } catch (error) {
    console.error('Error fetching user info:', error);
  }
};

const fetchOAuthStatus = async () => {
  try {
    const response = await fetch('/api/user/oauth-status', {
      credentials: 'include',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    if (response.ok) {
      const data = await response.json();
      setHasGmailOAuth(data.hasGmailOAuth || false);
    }
  } catch (error) {
    console.error('Error fetching OAuth status:', error);
  }
};

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getFileTypeDisplay = (mimeType: string) => {
    const typeMap: { [key: string]: string } = {
      'application/pdf': 'PDF',
      'application/msword': 'DOC',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
      'image/jpeg': 'JPEG',
      'image/png': 'PNG',
      'image/gif': 'GIF',
      'image/webp': 'WebP'
    };
    return typeMap[mimeType] || 'Unknown';
  };

  const formatDate = (dateString: string): string => {
    const date = new Date(dateString);
    const options: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
  };

  const handleDownloadResume = () => {
    if (uploadedResume) {
      // Create download link
      const link = document.createElement('a');
      link.href = `/api/user/file/${uploadedResume.id}`;
      link.download = uploadedResume.original_name;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }
  };

  const handleStartApplying = () => {
    console.log('Starting application process...');
    // This will trigger the bulk application process
    alert('Starting to apply to all jobs! This feature is coming soon.');
  };

  if (isLoading) {
    return (
      <div className="flex-1 p-8">
        <div className="w-full">
          <div className="flex gap-6">
            <div className="flex-shrink-0" style={{ width: '35%' }}>
              <Card className="p-6 animate-pulse">
                <div className="space-y-4">
                  <div className="h-6 bg-muted rounded w-1/2"></div>
                  <div className="h-32 bg-muted rounded"></div>
                </div>
              </Card>
            </div>
            <div className="flex-1">
              <Card className="p-6 animate-pulse">
                <div className="space-y-4">
                  <div className="h-6 bg-muted rounded w-1/2"></div>
                  <div className="space-y-3">
                    {[...Array(3)].map((_, i) => (
                      <div key={i} className="h-24 bg-muted rounded"></div>
                    ))}
                  </div>
                </div>
              </Card>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div ref={ref} className="flex flex-col min-h-screen">
      <div className="flex-1 p-8">
        <div className="w-full space-y-8">
          {/* Tabs */}
          <div className="flex items-center gap-8 border-b border-border">
            <button
              onClick={() => setActiveTab('matches')}
              className={`pb-4 px-2 text-sm font-medium transition-colors relative ${
                activeTab === 'matches'
                  ? 'text-primary'
                  : 'text-[#4A4A4A] hover:text-[#1A1A1A]'
              }`}
            >
              Your Job Matches
              {activeTab === 'matches' && (
                <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
              )}
            </button>
            <button
              onClick={() => setActiveTab('applications')}
              className={`pb-4 px-2 text-sm font-medium transition-colors relative ${
                activeTab === 'applications'
                  ? 'text-primary'
                  : 'text-[#4A4A4A] hover:text-[#1A1A1A]'
              }`}
            >
              Your Applications
              {activeTab === 'applications' && (
                <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
              )}
            </button>
            <button
              onClick={() => setActiveTab('settings')}
              className={`pb-4 px-2 text-sm font-medium transition-colors relative ${
                activeTab === 'settings'
                  ? 'text-primary'
                  : 'text-[#4A4A4A] hover:text-[#1A1A1A]'
              }`}
            >
              Settings
              {activeTab === 'settings' && (
                <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />
              )}
            </button>
          </div>

          {/* Main Content Grid */}
          <div className="flex gap-6 fade-in fade-in-delay-1 visible">
            {/* Left Column - Resume Preview */}
            <div className="sticky top-8 self-start space-y-6 flex-shrink-0" style={{ width: '35%' }}>
              <div className="flex items-center justify-between">
              </div>

              {uploadedResume ? (
                <Card className="p-6">
                  <div className="space-y-4">
                    <h2 className="text-xl font-semibold text-[#1A1A1A]">Resume</h2>
                    {/* File Info */}
                    <div className="flex items-center gap-4">
                      <div className="flex-shrink-0 p-3 bg-primary/10 rounded-lg">
                        <FileText className="h-8 w-8 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-small text-sm truncate dashboard-card-text">
                          {uploadedResume.original_name}
                        </p>
                        <div className="flex items-center gap-3 text-xs dashboard-card-text">
                          <span>{formatFileSize(uploadedResume.size)}</span>
                          <span>•</span>
                          <span>{getFileTypeDisplay(uploadedResume.type)}</span>
                        </div>
                        <div className="text-xs dashboard-card-text mt-1">
                          <span>Last Updated: {formatDate(uploadedResume.created_at)}</span>
                        </div>
                      </div>
                      <Badge variant="secondary">
                        {getFileTypeDisplay(uploadedResume.type)}
                      </Badge>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-3 pt-4 border-t border-border">
                      <Button
                        variant="outline"
                        className="flex-1 dashboard-card-text"
                      >
                        <Edit3 className="h-4 w-4 mr-2" />
                        Replace
                      </Button>
                    </div>
                  </div>
                </Card>
              ) : (
                <Card className="p-6 text-center">
                  <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                  <p className="dashboard-card-text">No resume found</p>
                  <Button variant="outline" className="mt-4">
                    Upload Resume
                  </Button>
                </Card>
              )}
              
              {/* Work Experience Section - Below FilePreview */}
              <Card className="p-6">
                <WorkExperience workExperience={workExperience} />
              </Card>
            </div>

            {/* Right Column - Job Queue */}
            <div className="flex-1 fade-in fade-in-delay-2 visible">
              <JobQueue userEmail={userEmail} hasGmailOAuth={hasGmailOAuth} />
            </div>
          </div>
        </div>
      </div>
      <Footer />
    </div>
  );
}