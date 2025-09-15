import { useState, useEffect } from "react";
import { FileText, Download, Edit3, CheckCircle2 } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { FilePreview } from "./FilePreview";
import { JobQueue } from "./JobQueue";
import { Footer } from "./Footer";
import WorkExperience from "./WorkExperience";

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
  const [uploadedResume, setUploadedResume] = useState<UploadedResume | null>(null);
  const [workExperience, setWorkExperience] = useState<WorkExperienceItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchUploadedResume();
    fetchWorkExperience();
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
        <div className="max-w-6xl mx-auto">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <Card className="p-6 animate-pulse">
              <div className="space-y-4">
                <div className="h-6 bg-muted rounded w-1/2"></div>
                <div className="h-32 bg-muted rounded"></div>
              </div>
            </Card>
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
    );
  }

  return (
    <div className="flex flex-col min-h-screen">
      <div className="flex-1 p-8">
        <div className="max-w-6xl mx-auto space-y-8">
          {/* Header */}
          <div className="text-center space-y-2">
            <h1 className="text-3xl font-bold text-white">Welcome to Your Job Dashboard</h1>
            <p className="text-white/90">
              Your resume is ready. Let's find you the perfect job opportunities.
            </p>
          </div>

          {/* Main Content Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            {/* Left Column - Resume Preview */}
            <div className="space-y-6">
              <div className="flex items-center justify-between">
                <h2 className="text-2xl font-semibold text-white">Your Resume</h2>
                <Badge variant="outline" className="flex items-center gap-2 bg-primary border-primary text-primary-foreground">
                  <CheckCircle2 className="h-3 w-3 text-primary-foreground" />
                  Ready
                </Badge>
              </div>

              {uploadedResume ? (
                <Card className="p-6">
                  <div className="space-y-4">
                    {/* File Info */}
                    <div className="flex items-center gap-4">
                      <div className="flex-shrink-0 p-3 bg-primary/10 rounded-lg">
                        <FileText className="h-8 w-8 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium truncate dashboard-card-text">
                          {uploadedResume.original_name}
                        </p>
                        <div className="flex items-center gap-3 text-sm dashboard-card-text">
                          <span>{formatFileSize(uploadedResume.size)}</span>
                          <span>•</span>
                          <span>{getFileTypeDisplay(uploadedResume.type)}</span>
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
                        onClick={handleDownloadResume}
                        className="flex-1 dashboard-card-text"
                      >
                        <Download className="h-4 w-4 mr-2" />
                        Download
                      </Button>
                      <Button
                        variant="outline"
                        className="flex-1 dashboard-card-text"
                      >
                        <Edit3 className="h-4 w-4 mr-2" />
                        Replace
                      </Button>
                    </div>

                    {/* File Preview Area */}
                    <div className="mt-6">
                      <FilePreview 
                        fileUrl={`/api/user/file/${uploadedResume.id}`}
                        fileName={uploadedResume.original_name}
                        fileType={uploadedResume.type}
                        fileSize={uploadedResume.size}
                        uploadedAt={new Date(uploadedResume.created_at).toLocaleDateString()}
                      />
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
            <div>
              <JobQueue onStartApplying={handleStartApplying} />
            </div>
          </div>
        </div>
      </div>
      <Footer />
    </div>
  );
}