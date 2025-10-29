import { useState, useEffect } from "react";
import { FileText, Download, Edit3, CheckCircle2, Mail, Edit2, Briefcase, ChevronLeft, ChevronRight, DollarSign, MapPin, BriefcaseIcon, User, CreditCard, Lock, Trash2 } from "lucide-react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Switch } from "./ui/switch";
import { Label } from "./ui/label";
import { Input } from "./ui/input";
import { Slider } from "./ui/slider";
import { ApplicationCard } from "./ApplicationCard";
import { JobQueue } from "./JobQueue";
import { Footer } from "./Footer";
import WorkExperience from "./WorkExperience";
import { useScrollAnimation } from "./hooks/useScrollAnimation";
import { FileUpload } from "./FileUpload";
import { APIProvider } from '@vis.gl/react-google-maps';

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

type ApplicationStatus = "Applied" | "No Response" | "Delivery Error";

interface ApplicationItem {
  title: string;
  company: string;
  salaryRange?: string;
  location?: string;
  appliedDate: string;
  status: ApplicationStatus;
  description?: string;
  applicationDetails?: {
    platform?: string;
    applicationId?: string;
    coverLetter?: boolean;
  };
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
  const [userApplications, setUserApplications] = useState<ApplicationItem[]>([]);
  const [currentPage, setCurrentPage] = useState(1);
  const [showUploadView, setShowUploadView] = useState(false);

  // Settings state
  const [notifyNewMatches, setNotifyNewMatches] = useState(true);
  const [notifyApplicationUpdates, setNotifyApplicationUpdates] = useState(true);
  const [emailDigestFrequency, setEmailDigestFrequency] = useState<'immediate' | 'daily' | 'weekly'>('daily');
  const [minSalary, setMinSalary] = useState([100]);
  const [maxSalary, setMaxSalary] = useState([150]);
  const [preferredLocation, setPreferredLocation] = useState('');
  const [preferredJobTitle, setPreferredJobTitle] = useState('');
  const [employmentTypes, setEmploymentTypes] = useState({ fullTime: true, contract: false, partTime: false });
  const [workArrangement, setWorkArrangement] = useState({ remote: true, hybrid: true, onsite: false });
  const [willingToRelocate, setWillingToRelocate] = useState(false);
  const [queueAutoApply, setQueueAutoApply] = useState(false);
  const [autonomousAutoApply, setAutonomousAutoApply] = useState(false);
  const [maxApplicationsPerDay, setMaxApplicationsPerDay] = useState(10);
  const [showToRecruiters, setShowToRecruiters] = useState(true);
  const [hideFromCurrentEmployer, setHideFromCurrentEmployer] = useState(false);
  const [timezone, setTimezone] = useState('America/New_York');
  const [matchEmailFrequency, setMatchEmailFrequency] = useState<'daily' | 'weekly' | 'never'>('weekly');
  const [isSettingsLoading, setIsSettingsLoading] = useState(false);
  const [isSavingSettings, setIsSavingSettings] = useState(false);

  // Account info state
  const [userName, setUserName] = useState('');
  const [userCredits, setUserCredits] = useState(0);
  const [hasSubscription, setHasSubscription] = useState(false);

  const itemsPerPage = 10;
  useEffect(() => {
    fetchUploadedResume();
    fetchWorkExperience();
    fetchUserInfo();
    fetchOAuthStatus();
    fetchUserApplications();
  }, []);

  // Fetch settings when settings tab is opened
  useEffect(() => {
    if (activeTab === 'settings') {
      fetchUserSettings();
      fetchAccountInfo();
    }
  }, [activeTab]);

  // Initialize Google Places Autocomplete
  useEffect(() => {
    if (activeTab === 'settings') {
      const initMap = async () => {
        try {
          // Wait for DOM to be ready
          await new Promise(resolve => setTimeout(resolve, 100));

          const placeField = document.getElementById("placesElem");
          if (!placeField) {
            return;
          }

          // Clear any existing content
          placeField.innerHTML = '';

          // Request needed libraries
          await google.maps.importLibrary("places") as google.maps.PlacesLibrary;

          // Create the autocomplete element
          //@ts-ignore
          const placeAutocomplete = new google.maps.places.PlaceAutocompleteElement();

          placeField.appendChild(placeAutocomplete);

          // Add the gmp-placeselect listener
          //@ts-ignore
          placeAutocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
            const place = placePrediction.toPlace();
            await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
            setPreferredLocation(place.formattedAddress || place.displayName);
          });
        } catch (error) {
          console.error('Error initializing places autocomplete:', error);
        }
      };

      initMap();
    }
  }, [activeTab]);

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
      setUserName(data.formData?.firstName && data.formData?.lastName
        ? `${data.formData.firstName} ${data.formData.lastName}`
        : '');
    }
  } catch (error) {
    console.error('Error fetching user info:', error);
  }
};

const fetchAccountInfo = async () => {
  try {
    const response = await fetch('/api/user/credits', {
      credentials: 'include',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    });

    if (response.ok) {
      const data = await response.json();
      setUserCredits(data.credits || 0);
      setHasSubscription(data.has_subscription || false);
    }
  } catch (error) {
    console.error('Error fetching account info:', error);
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
    // This will trigger the bulk application process
    alert('Starting to apply to all jobs! This feature is coming soon.');
  };

  const fetchUserApplications = async () => {
    try {
      const response = await fetch('/api/applications', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        // Backend returns paginated data
        if (data.data) {
          // Map backend application data to frontend format
          const mappedApplications = data.data.map((app: any) => ({
            title: app.lead?.job_title || app.lead?.title || 'Unknown Position',
            company: app.lead?.company || 'Unknown Company',
            salaryRange: app.lead?.pay_range,
            location: app.lead?.location,
            appliedDate: new Date(app.created_at).toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'short',
              day: 'numeric'
            }),
            status: mapApplicationStatus(app.status),
            description: app.lead?.description,
            applicationDetails: {
              platform: app.application_method === 'email_based' ? 'Email' : 'Direct Application',
              applicationId: `APP-${app.id}`,
              coverLetter: !!app.cover_letter
            }
          }));
          setUserApplications(mappedApplications);
        }
      } else if (response.status === 401) {
        // User not authenticated
      }
    } catch (error) {
      console.error('Error fetching applications:', error);
    }
  };

  const fetchUserSettings = async () => {
    try {
      setIsSettingsLoading(true);
      const response = await fetch('/api/user/settings', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        const settings = data.settings || data; // Handle both new and old response formats
        const user = data.user || {};

        // Update all settings state
        setNotifyNewMatches(settings.notify_new_matches ?? true);
        setNotifyApplicationUpdates(settings.notify_application_updates ?? true);
        setEmailDigestFrequency(settings.email_digest_frequency ?? 'daily');
        setMinSalary([settings.min_salary ?? 100]);
        setMaxSalary([settings.max_salary ?? 150]);
        setPreferredLocation(settings.preferred_location ?? '');
        setPreferredJobTitle(settings.preferred_job_title ?? '');
        setEmploymentTypes(settings.employment_types ?? { fullTime: true, contract: false, partTime: false });
        setWorkArrangement(settings.work_arrangement ?? { remote: true, hybrid: true, onsite: false });
        setWillingToRelocate(settings.willing_to_relocate ?? false);
        setQueueAutoApply(settings.queue_auto_apply ?? false);
        setAutonomousAutoApply(settings.autonomous_auto_apply ?? false);
        setMaxApplicationsPerDay(settings.max_applications_per_day ?? 10);
        setShowToRecruiters(settings.show_to_recruiters ?? true);
        setHideFromCurrentEmployer(settings.hide_from_current_employer ?? false);

        // Update user-level preferences
        setTimezone(user.timezone ?? 'America/New_York');
        setMatchEmailFrequency(user.match_email_frequency ?? 'weekly');
      }
    } catch (error) {
      console.error('Error fetching user settings:', error);
    } finally {
      setIsSettingsLoading(false);
    }
  };

  const handleSaveSettings = async () => {
    try {
      setIsSavingSettings(true);

      const settingsData = {
        notify_new_matches: notifyNewMatches,
        notify_application_updates: notifyApplicationUpdates,
        email_digest_frequency: emailDigestFrequency,
        min_salary: minSalary[0],
        max_salary: maxSalary[0],
        preferred_location: preferredLocation,
        preferred_job_title: preferredJobTitle,
        employment_types: employmentTypes,
        work_arrangement: workArrangement,
        willing_to_relocate: willingToRelocate,
        queue_auto_apply: queueAutoApply,
        autonomous_auto_apply: autonomousAutoApply,
        max_applications_per_day: maxApplicationsPerDay,
        show_to_recruiters: showToRecruiters,
        hide_from_current_employer: hideFromCurrentEmployer,
        timezone: timezone,
        match_email_frequency: matchEmailFrequency,
      };

      // Fetch CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const csrfToken = await tokenResponse.json();

      const headers: Record<string, string> = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken.token,
      };

      const response = await fetch('/api/user/settings', {
        method: 'POST',
        credentials: 'include',
        headers,
        body: JSON.stringify(settingsData),
      });

      if (response.ok) {
        alert('Settings saved successfully!');
      } else {
        const error = await response.json();
        console.error('Save failed:', error);
        alert(`Failed to save settings: ${error.error || error.message || 'Unknown error'}`);
      }
    } catch (error) {
      console.error('Error saving settings:', error);
      alert('Failed to save settings. Please try again.');
    } finally {
      setIsSavingSettings(false);
    }
  };

  const mapApplicationStatus = (status: string): ApplicationStatus => {
    switch (status) {
      case 'submitted':
      case 'completed':
        return 'Applied';
      case 'failed':
      case 'error':
        return 'Delivery Error';
      case 'pending':
      case 'processing':
      default:
        return 'No Response';
    }
  };

  // Pagination calculations
  const totalPages = Math.ceil(userApplications.length / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentApplications = userApplications.slice(startIndex, endIndex);

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  // Show FileUpload view if requested
  if (showUploadView) {
    return (
      <div className="flex flex-col min-h-screen">
        <FileUpload
          onAuthRequired={() => {}}
          onShowLogin={() => {}}
          isAuthenticated={true}
          onUploadSuccess={() => {
            setShowUploadView(false);
            fetchUploadedResume();
            window.location.reload();
          }}
        />
        <Footer />
      </div>
    );
  }

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
    <div ref={ref} className="flex flex-col min-h-screen dashboard-bg">
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
          {/* Dashboard Content */}
        {activeTab === 'matches' && (
          <div className="flex gap-6 fade-in fade-in-delay-1 visible">
            {/* Left Column - Resume Preview */}
            <div className="sticky top-8 self-start space-y-6 flex-shrink-0" style={{ width: '35%' }}>
              <div className="flex items-center justify-between">
              </div>

              {uploadedResume ? (
                <Card className="p-6">
                  <div className="space-y-4">
                    {/* Header with Edit button */}
                    <div className="flex items-center justify-between">
                      <h2 className="text-xl font-semibold text-[#1A1A1A]">Resume</h2>
                      <button className="text-sm font-medium text-primary hover:text-primary/80 transition-colors flex items-center gap-1">
                        <Edit2 className="h-4 w-4" />
                        Edit
                      </button>
                    </div>
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
                  </div>
                </Card>
              ) : (
                <Card className="p-6 text-center">
                  <FileText className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                  <p className="dashboard-card-text">No resume found</p>
                    <Button
                    onClick={() => setShowUploadView(true)}
                    className="bg-primary hover:bg-primary/90"
                    >
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
        )}
        
        {/* Your Applications Tab */}
        {activeTab === 'applications' && (
          <div className="space-y-4 pt-8">
            {/* Header */}
            <div className="flex items-center justify-between">
              <div>
                <h2 className="font-semibold text-foreground">Your Applications</h2>
                <p className="text-sm text-muted-foreground mt-1">
                  Track the status of your job applications
                </p>
              </div>
              <div className="text-sm text-muted-foreground">
                {userApplications.length} total application{userApplications.length !== 1 ? 's' : ''}
              </div>
            </div>

            {/* Applications List */}
            {userApplications.length > 0 ? (
              <>
                <div className="space-y-2">
                  {currentApplications.map((application, index) => (
                    <ApplicationCard
                      key={startIndex + index}
                      title={application.title}
                      company={application.company}
                      salaryRange={application.salaryRange}
                      location={application.location}
                      appliedDate={application.appliedDate}
                      status={application.status}
                      description={application.description}
                      applicationDetails={application.applicationDetails}
                    />
                  ))}
                </div>

                {/* Pagination Controls */}
                {totalPages > 1 && (
                  <div className="flex items-center justify-center gap-2 pt-6">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handlePageChange(currentPage - 1)}
                      disabled={currentPage === 1}
                      className="h-8 w-8 p-0"
                    >
                      <ChevronLeft size={16} />
                    </Button>

                    <div className="flex items-center gap-1">
                      {Array.from({ length: totalPages }, (_, i) => i + 1).map((page) => (
                        <Button
                          key={page}
                          variant={currentPage === page ? "default" : "outline"}
                          size="sm"
                          onClick={() => handlePageChange(page)}
                          className="h-8 w-8 p-0"
                        >
                          {page}
                        </Button>
                      ))}
                    </div>

                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handlePageChange(currentPage + 1)}
                      disabled={currentPage === totalPages}
                      className="h-8 w-8 p-0"
                    >
                      <ChevronRight size={16} />
                    </Button>
                  </div>
                )}
              </>
            ) : (
              <div className="text-center py-4">
                <Briefcase size={48} className="mx-auto text-muted-foreground mb-1" />
                <h3 className="font-semibold text-foreground mb-2">No Applications Yet</h3>
                <p className="text-muted-foreground">Once you start applying to jobs, they'll appear here.</p>
              </div>
            )}
          </div>
        )}

        {/* Settings Tab */}
        {activeTab === 'settings' && (
          <div className="max-w-7xl">
            {isSettingsLoading ? (
              <Card className="p-6">
                <div className="flex items-center justify-center py-8">
                  <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                    <p className="text-muted-foreground">Loading settings...</p>
                  </div>
                </div>
              </Card>
            ) : (
              <>
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column - Settings */}
                <div className="lg:col-span-2 space-y-6">
            {/* Email Notifications */}
            <Card className="p-6">
              <h3 className="font-semibold text-foreground mb-4 flex items-center gap-2">
                <Mail size={20} />
                Email Notifications
              </h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="notify-matches" className="text-sm font-medium">New job matches</Label>
                    <p className="text-sm text-muted-foreground">Get notified when new jobs match your profile</p>
                  </div>
                  <Switch
                    id="notify-matches"
                    checked={notifyNewMatches}
                    onCheckedChange={setNotifyNewMatches}
                  />
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="notify-updates" className="text-sm font-medium">Application status updates</Label>
                    <p className="text-sm text-muted-foreground">Get notified when employers respond</p>
                  </div>
                  <Switch
                    id="notify-updates"
                    checked={notifyApplicationUpdates}
                    onCheckedChange={setNotifyApplicationUpdates}
                  />
                </div>

                <div className="pt-4 border-t">
                  <Label htmlFor="match-email-frequency" className="text-sm font-medium">Match Email Digest</Label>
                  <p className="text-sm text-muted-foreground mb-3">How often should we send you job match summaries?</p>
                  <select
                    id="match-email-frequency"
                    value={matchEmailFrequency}
                    onChange={(e) => setMatchEmailFrequency(e.target.value as 'daily' | 'weekly' | 'never')}
                    className="w-full px-3 py-2 border border-input bg-background rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                  >
                    <option value="daily">Daily (best for active job seekers)</option>
                    <option value="weekly">Weekly (recommended)</option>
                    <option value="never">Never</option>
                  </select>
                </div>

                <div>
                  <Label htmlFor="timezone" className="text-sm font-medium">Timezone</Label>
                  <p className="text-sm text-muted-foreground mb-3">Emails will be sent at 8am in your timezone</p>
                  <select
                    id="timezone"
                    value={timezone}
                    onChange={(e) => setTimezone(e.target.value)}
                    className="w-full px-3 py-2 border border-input bg-background rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                  >
                    <option value="America/New_York">Eastern Time (ET)</option>
                    <option value="America/Chicago">Central Time (CT)</option>
                    <option value="America/Denver">Mountain Time (MT)</option>
                    <option value="America/Los_Angeles">Pacific Time (PT)</option>
                    <option value="America/Anchorage">Alaska Time (AKT)</option>
                    <option value="Pacific/Honolulu">Hawaii Time (HT)</option>
                    <option value="Europe/London">London (GMT)</option>
                    <option value="Europe/Paris">Central European Time (CET)</option>
                    <option value="Asia/Tokyo">Tokyo (JST)</option>
                    <option value="Asia/Shanghai">China (CST)</option>
                    <option value="Asia/Dubai">Dubai (GST)</option>
                    <option value="Australia/Sydney">Sydney (AEDT)</option>
                  </select>
                </div>
              </div>
            </Card>

            {/* Job Preferences */}
            <Card className="p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold text-foreground flex items-center gap-2">
                  <BriefcaseIcon size={20} />
                  Job Preferences
                </h3>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    setMinSalary([0]);
                    setMaxSalary([0]);
                    setPreferredLocation('');
                    setPreferredJobTitle('');
                    setEmploymentTypes({ fullTime: false, contract: false, partTime: false });
                    setWorkArrangement({ remote: false, hybrid: false, onsite: false });
                  }}
                  className="text-destructive hover:text-destructive hover:bg-destructive/10"
                >
                  Clear All Filters
                </Button>
              </div>
              <div className="space-y-6">
                {/* Salary Range */}
                <div className="space-y-4">
                  <div>
                    <Label className="text-sm font-medium flex items-center gap-2">
                      <DollarSign size={16} />
                      Salary Range (in thousands)
                    </Label>
                    <p className="text-sm text-muted-foreground">Set your desired salary range</p>
                  </div>
                  <div className="space-y-4">
                    <div>
                      <Label htmlFor="min-salary" className="text-sm text-muted-foreground">Minimum: ${minSalary[0]}k</Label>
                      <Slider
                        id="min-salary"
                        min={0}
                        max={500}
                        step={10}
                        value={minSalary}
                        onValueChange={setMinSalary}
                        className="mt-2"
                      />
                    </div>
                    <div>
                      <Label htmlFor="max-salary" className="text-sm text-muted-foreground">Maximum: ${maxSalary[0]}k</Label>
                      <Slider
                        id="max-salary"
                        min={0}
                        max={500}
                        step={10}
                        value={maxSalary}
                        onValueChange={setMaxSalary}
                        className="mt-2"
                      />
                    </div>
                  </div>
                </div>

                {/* Location */}
                 <APIProvider apiKey="AIzaSyDypaJLMOb2FNFMHNkJTdIca5TQYcZS8T0">
                <div>
                  <Label htmlFor="location" className="text-sm font-medium flex items-center gap-2">
                    <MapPin size={16} />
                    Preferred Location
                  </Label>
                  <p className="text-sm text-muted-foreground mb-2">Enter your preferred work location</p>
                  <div id="placesElem"></div>
                  {preferredLocation && (
                    <div className="flex items-center justify-between mt-2">
                      <p className="text-sm text-primary-text">Selected: {preferredLocation}</p>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setPreferredLocation('')}
                        className="text-destructive hover:text-destructive hover:bg-destructive/10"
                      >
                        Clear
                      </Button>
                    </div>
                  )}
                </div>
                </APIProvider>

                {/* Job Title */}
                <div>
                  <Label htmlFor="job-title" className="text-sm font-medium">Preferred Job Title</Label>
                  <p className="text-sm text-muted-foreground mb-2">What role are you looking for?</p>
                  <Input
                    id="job-title"
                    placeholder="e.g., Senior Software Engineer"
                    value={preferredJobTitle}
                    onChange={(e) => setPreferredJobTitle(e.target.value)}
                  />
                </div>

                {/* Employment Type */}
                <div>
                  <Label className="text-sm font-medium">Employment Type</Label>
                  <p className="text-sm text-muted-foreground mb-3">Select preferred employment types</p>
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <Label htmlFor="full-time" className="text-sm font-normal">Full-time</Label>
                      <Switch
                        id="full-time"
                        checked={employmentTypes.fullTime}
                        onCheckedChange={(checked: boolean) => setEmploymentTypes({ ...employmentTypes, fullTime: checked })}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <Label htmlFor="contract" className="text-sm font-normal">Contract</Label>
                      <Switch
                        id="contract"
                        checked={employmentTypes.contract}
                        onCheckedChange={(checked: boolean) => setEmploymentTypes({ ...employmentTypes, contract: checked })}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <Label htmlFor="part-time" className="text-sm font-normal">Part-time</Label>
                      <Switch
                        id="part-time"
                        checked={employmentTypes.partTime}
                        onCheckedChange={(checked: boolean) => setEmploymentTypes({ ...employmentTypes, partTime: checked })}
                      />
                    </div>
                  </div>
                </div>

                {/* Work Arrangement */}
                <div>
                  <Label className="text-sm font-medium">Work Arrangement</Label>
                  <p className="text-sm text-muted-foreground mb-3">Select preferred work arrangements</p>
                  <div className="space-y-2">
                    <div className="flex items-center justify-between">
                      <Label htmlFor="remote" className="text-sm font-normal">Remote</Label>
                      <Switch
                        id="remote"
                        checked={workArrangement.remote}
                        onCheckedChange={(checked: boolean) => setWorkArrangement({ ...workArrangement, remote: checked })}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <Label htmlFor="hybrid" className="text-sm font-normal">Hybrid</Label>
                      <Switch
                        id="hybrid"
                        checked={workArrangement.hybrid}
                        onCheckedChange={(checked: boolean) => setWorkArrangement({ ...workArrangement, hybrid: checked })}
                      />
                    </div>
                    <div className="flex items-center justify-between">
                      <Label htmlFor="onsite" className="text-sm font-normal">On-site</Label>
                      <Switch
                        id="onsite"
                        checked={workArrangement.onsite}
                        onCheckedChange={(checked: boolean) => setWorkArrangement({ ...workArrangement, onsite: checked })}
                      />
                    </div>
                  </div>
                </div>

                {/* Willing to Relocate */}
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="relocate" className="text-sm font-medium">Willing to relocate</Label>
                    <p className="text-sm text-muted-foreground">Show jobs that require relocation</p>
                  </div>
                  <Switch
                    id="relocate"
                    checked={willingToRelocate}
                    onCheckedChange={setWillingToRelocate}
                  />
                </div>
              </div>
            </Card>

            {/* Application Settings */}
            <Card className="p-6">
              <h3 className="font-semibold text-foreground mb-4">Application Settings</h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="queue-auto-apply" className="text-sm font-medium">Queue auto-apply</Label>
                    <p className="text-sm text-muted-foreground">Automatically apply to jobs in your queue</p>
                  </div>
                  <Switch
                    id="queue-auto-apply"
                    checked={queueAutoApply}
                    onCheckedChange={setQueueAutoApply}
                  />
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="autonomous-auto-apply" className="text-sm font-medium">Autonomous auto-apply</Label>
                    <p className="text-sm text-muted-foreground">Automatically apply to new matches without review</p>
                  </div>
                  <Switch
                    id="autonomous-auto-apply"
                    checked={autonomousAutoApply}
                    onCheckedChange={setAutonomousAutoApply}
                  />
                </div>
                <div>
                  <Label htmlFor="max-apps" className="text-sm font-medium">Max applications per day</Label>
                  <p className="text-sm text-muted-foreground mb-2">Limit daily applications to avoid spam</p>
                  <Input
                    id="max-apps"
                    type="number"
                    min="1"
                    max="100"
                    value={maxApplicationsPerDay}
                    onChange={(e) => setMaxApplicationsPerDay(parseInt(e.target.value) || 10)}
                  />
                </div>
              </div>
            </Card>

            {/* Privacy Settings */}
            <Card className="p-6">
              <h3 className="font-semibold text-foreground mb-4">Privacy Settings</h3>
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="show-recruiters" className="text-sm font-medium">Show me to recruiters on AppliFlow</Label>
                    <p className="text-sm text-muted-foreground">Allow recruiters to discover your profile</p>
                  </div>
                  <Switch
                    id="show-recruiters"
                    checked={showToRecruiters}
                    onCheckedChange={setShowToRecruiters}
                  />
                </div>
                <div className="flex items-center justify-between">
                  <div className="flex-1">
                    <Label htmlFor="hide-employer" className="text-sm font-medium">Hide from current employer</Label>
                    <p className="text-sm text-muted-foreground">Don't show your profile to your current employer</p>
                  </div>
                  <Switch
                    id="hide-employer"
                    checked={hideFromCurrentEmployer}
                    onCheckedChange={setHideFromCurrentEmployer}
                  />
                </div>
              </div>
            </Card>

            {/* Account Actions */}
            <Card className="p-6">
              <h3 className="font-semibold text-foreground mb-4">Account</h3>
              <div className="space-y-4">
                <div>
                  <Label className="text-sm font-medium">Connected Accounts</Label>
                  <p className="text-sm text-muted-foreground mb-3">Manage your connected services</p>
                  <div className="flex items-center gap-3">
                    <Badge variant={hasGmailOAuth ? "default" : "secondary"}>
                      {hasGmailOAuth ? "✓ Google Connected" : "Google Not Connected"}
                    </Badge>
                  </div>
                </div>
                <div className="pt-4 border-t">
                  <Button variant="outline" className="w-full">
                    Export Application Data
                  </Button>
                </div>
              </div>
            </Card>

                </div>

                {/* Right Column - Account Info */}
                <div className="space-y-6">
                  <Card className="p-6">
                    <h3 className="font-semibold text-foreground mb-4 flex items-center gap-2">
                      <User size={20} />
                      Your Account Info
                    </h3>
                    <div className="space-y-4">
                      {/* Full Name */}
                      <div>
                        <Label htmlFor="full-name" className="text-sm font-medium">Full Name</Label>
                        <Input
                          id="full-name"
                          value={userName}
                          onChange={(e) => setUserName(e.target.value)}
                          placeholder="Enter your full name"
                          className="mt-1"
                        />
                      </div>

                      {/* Account Status */}
                      <div>
                        <Label className="text-sm font-medium">Account Status</Label>
                        <div className="mt-1">
                          <Badge
                            style={hasSubscription ? { backgroundColor: '#8B5CF6', color: 'white' } : undefined}
                            variant={hasSubscription ? undefined : "secondary"}
                            className={hasSubscription ? "border-transparent" : ""}
                          >
                            {hasSubscription ? "Subscribed" : "Unsubscribed"}
                          </Badge>
                        </div>
                      </div>

                      {/* Token Balance */}
                      <div>
                        <Label className="text-sm font-medium flex items-center gap-2">
                          <CreditCard size={16} />
                          Token Balance
                        </Label>
                        <div className="mt-2">
                          <div className="text-2xl font-semibold text-foreground">
                            {hasSubscription ? (
                              <span className="text-green-600">Unlimited</span>
                            ) : (
                              userCredits
                            )}
                          </div>
                          {!hasSubscription && (
                            <a href="#pricing" className="text-sm text-primary hover:underline mt-2 inline-block">
                              Get More Tokens
                            </a>
                          )}
                        </div>
                      </div>

                      {/* Divider */}
                      <div className="border-t pt-4 space-y-3">
                        {/* Change Password */}
                        <Button variant="outline" className="w-full justify-start" asChild>
                          <a href="#change-password" className="flex items-center gap-2">
                            <Lock size={16} />
                            Change Password
                          </a>
                        </Button>

                        {/* Delete Account */}
                        <Button
                          variant="outline"
                          className="w-full justify-start text-destructive hover:text-destructive hover:bg-destructive/10"
                        >
                          <Trash2 size={16} className="mr-2" />
                          Delete Account
                        </Button>
                      </div>
                    </div>
                  </Card>
                </div>
              </div>

              {/* Save Button */}
              <div className="flex justify-end py-4">
                <Button
                  className="bg-primary hover:bg-primary/90"
                  onClick={handleSaveSettings}
                  disabled={isSavingSettings}
                >
                  {isSavingSettings ? 'Saving...' : 'Save Settings'}
                </Button>
              </div>
              </>
            )}
          </div>
        )}
        </div>
      </div>
      <Footer />
    </div>
  );
}