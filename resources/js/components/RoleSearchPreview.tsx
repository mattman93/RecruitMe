import { useState, useEffect, useRef } from "react";
import { Button } from "./ui/button";
import { Search, Building2, MapPin, DollarSign, TrendingUp } from "lucide-react";

interface Job {
  id: number;
  job_title: string;
  company: string;
  location: string;
  pay_range: string;
  description: string;
  relevance_score: number;
  created_at: string;
  source_url: string;
  company_website?: string;
}

interface JobSearchResponse {
  data: Job[];
  total: number;
  total_matches: number;
  show_approximate: boolean;
  role: string;
  hours: number;
  message: string;
}

interface RoleSearchPreviewProps {
  onUploadResume?: () => void;
}

const POPULAR_ROLES = [
  "Software Engineer",
  "Product Manager",
  "Data Analyst",
  "UX Designer",
  "DevOps Engineer",
  "Full Stack Developer"
];

export function RoleSearchPreview({ onUploadResume }: RoleSearchPreviewProps) {
  const [selectedRole, setSelectedRole] = useState<string>("");
  const [searchInput, setSearchInput] = useState<string>("");
  const [jobs, setJobs] = useState<Job[]>([]);
  const [totalMatches, setTotalMatches] = useState<number>(0);
  const [showApproximate, setShowApproximate] = useState<boolean>(false);
  const [isLoading, setIsLoading] = useState(false);
  const [showResults, setShowResults] = useState(false);
  const [message, setMessage] = useState<string>("");
  const resultsRef = useRef<HTMLDivElement>(null);


  const searchJobs = async (role: string) => {
    if (!role || role.length < 2) return;

    setIsLoading(true);
    setShowResults(false);

    try {
      const response = await fetch(`/api/leads/preview-by-role?role=${encodeURIComponent(role)}&limit=10`, {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch jobs');
      }

      const data: JobSearchResponse = await response.json();
      setJobs(data.data || []);
      setTotalMatches(data.total_matches || 0);
      setShowApproximate(data.show_approximate || false);
      setMessage(data.message || "");

      // Animate in results after API completes
      setTimeout(() => {
        setShowResults(true);
      }, 300);
    } catch (error) {
      console.error('Failed to search jobs:', error);
      setJobs([]);
      setTotalMatches(0);
      setShowApproximate(false);
      setMessage("Failed to load jobs. Please try again.");
    } finally {
      setIsLoading(false);
    }
  };

  const handleRoleSelect = (role: string) => {
    setSelectedRole(role);
    setSearchInput(role);

    // Scroll to loading section immediately
    setTimeout(() => {
      if (resultsRef.current) {
        resultsRef.current.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }, 100);

    searchJobs(role);
  };

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchInput.trim()) {
      handleRoleSelect(searchInput.trim());
    }
  };

  const truncateDescription = (text: string, maxLength: number = 150) => {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength).trim() + '...';
  };

  return (
    <div className="w-full max-w-5xl mx-auto px-4 py-8">
      {/* Title and Search Section */}
      <div className="text-center mb-8">

        {/* Search Input */}
        <form onSubmit={handleSearchSubmit} className="max-w-2xl mx-auto mb-6">
          <div className="relative">
            <Search className="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 h-5 w-5" />
            <input
              type="text"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              placeholder="Search for a role (e.g., Senior Product Manager)"
              className="w-full pl-12 pr-4 py-4 rounded-xl text-lg border-2 border-white/30 bg-white/10 backdrop-blur-sm text-white placeholder-white/60 focus:outline-none focus:border-white/60 transition-all"
            />
          </div>
        </form>

        {/* Quick Select Buttons */}
        <div className="flex flex-wrap justify-center gap-3 mb-8">
          {POPULAR_ROLES.map((role) => (
            <Button
              key={role}
              onClick={() => handleRoleSelect(role)}
              variant={selectedRole === role ? "default" : "outline"}
              className={`
                px-4 py-2 rounded-full font-medium transition-all duration-300
                ${selectedRole === role
                  ? 'bg-white text-indigo-600 hover:bg-white/90'
                  : 'bg-white/10 text-white border-white/30 hover:bg-white/20 hover:border-white/50'
                }
              `}
            >
              {role}
            </Button>
          ))}
        </div>
      </div>

      {/* Results Container (for scroll target) */}
      <div ref={resultsRef}>
        {/* Loading State */}
        {isLoading && (
          <div className="text-center py-12">
            <div className="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent mx-auto mb-4"></div>
            <p className="text-white font-medium">Finding matches for {selectedRole}...</p>
          </div>
        )}

        {/* Results Section */}
        {!isLoading && jobs.length > 0 && (
          <div
            className="transition-all duration-2000 ease-out"
            style={{
              opacity: showResults ? 1 : 0,
              transform: showResults ? 'translateY(0)' : 'translateY(20px)',
              visibility: showResults ? 'visible' : 'hidden'
            }}
          >
          {/* Stats Header */}
          {totalMatches > 0 && (
            <div className="bg-white/10 backdrop-blur-sm rounded-xl p-6 mb-6 border border-white/20">
              <div className="flex items-center justify-between flex-wrap gap-4">
                <div>
                  <h3 className="text-2xl font-bold text-white mb-1">
                    {totalMatches}{showApproximate ? '+' : ''} matching role{totalMatches !== 1 ? 's' : ''}
                  </h3>
                  <p style={{ color: 'rgba(255, 255, 255, 0.9)' }}>{message}</p>
                </div>
                <Button
                  onClick={onUploadResume}
                  className="bg-white text-indigo-600 hover:bg-white/90 px-6 py-3 font-semibold rounded-lg"
                >
                  Upload Resume to Apply
                </Button>
              </div>
            </div>
          )}

          {/* Job Cards */}
          {jobs.length > 0 ? (
            <div className="grid gap-4">
              {jobs.map((job, index) => (
                <div
                  key={job.id}
                  className="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20 hover:bg-white/15 transition-all duration-300 hover:scale-[1.02] hover:shadow-xl"
                  style={{
                    animation: `slideInUp 0.5s ease-out ${index * 0.1}s both`
                  }}
                >
                  <div className="flex items-start gap-4">
                    {/* Company Logo/Icon */}
                    <div className="flex-shrink-0">
                      <div className="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center">
                        <Building2 className="h-6 w-6 text-white" />
                      </div>
                    </div>

                    {/* Job Details */}
                    <div className="flex-1 min-w-0">
                      <div className="flex items-start justify-between gap-4 mb-3">
                        <div className="flex-1 min-w-0">
                          <h4 className="text-xl font-bold text-white mb-1 text-left">
                            {job.job_title}
                          </h4>
                          <div className="flex items-center gap-2 text-white text-sm flex-wrap">
                            <Building2 className="h-4 w-4 text-white" />
                            <span className="text-white">{job.company}</span>
                            {job.location && (
                              <>
                                <span className="text-white">•</span>
                                <MapPin className="h-4 w-4 text-white" />
                                <span className="text-white">{job.location}</span>
                              </>
                            )}
                          </div>
                        </div>

                        {/* Relevance Score */}
                        <div className="flex-shrink-0 bg-white/20 rounded-lg px-4 py-2 text-center">
                          <div className="flex items-center gap-1 justify-center">
                            <TrendingUp className="h-4 w-4 text-white" />
                            <span className="text-2xl font-bold text-white">
                              {job.relevance_score}
                            </span>
                          </div>
                          <div className="text-xs text-white whitespace-nowrap">Match Score</div>
                        </div>
                      </div>

                      {/* Description */}
                      <p className="text-white text-sm mb-3 line-clamp-2">
                        {truncateDescription(job.description)}
                      </p>

                      {/* Footer */}
                      <div className="flex items-center justify-between gap-4">
                        {job.pay_range && job.pay_range.toLowerCase() !== 'not specified' ? (
                          <div className="flex items-center gap-2 text-sm">
                            <DollarSign className="h-4 w-4 text-white" />
                            <span className="text-white">{job.pay_range}</span>
                          </div>
                        ) : (
                          <div className="flex items-center gap-2 text-sm" style={{ color: 'rgba(255, 255, 255, 0.6)' }}>
                            <DollarSign className="h-4 w-4" style={{ color: 'rgba(255, 255, 255, 0.6)' }} />
                            <span style={{ color: 'rgba(255, 255, 255, 0.6)' }}>Not specified</span>
                          </div>
                        )}
                        <a
                          href={job.source_url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-white hover:text-white/80 text-sm font-medium underline ml-auto"
                        >
                          View Details →
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="text-center py-12">
              <p className="text-white/80 text-lg">{message || "No jobs found. Try a different role!"}</p>
            </div>
          )}

          {/* CTA at bottom */}
          {jobs.length > 0 && (
            <div className="text-center mt-8">
              <Button
                onClick={onUploadResume}
                className="bg-white text-indigo-600 hover:bg-white/90 px-8 py-4 text-lg font-semibold rounded-lg"
              >
                Want us to apply to these for you? Upload your resume →
              </Button>
            </div>
          )}
          </div>
        )}
      </div>

      {/* CSS for animations */}
      <style>{`
        @keyframes slideInUp {
          from {
            opacity: 0;
            transform: translateY(20px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
      `}</style>
    </div>
  );
}
