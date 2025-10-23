import { useState } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from "./ui/dialog";
import { Button } from "./ui/button";
import { Badge } from "./ui/badge";
import { Card } from "./ui/card";
import { Sparkles, MapPin, Briefcase, TrendingUp, Lock, ArrowRight, X } from "lucide-react";

interface JobMatch {
  id: number;
  job_title: string;
  company: string;
  location: string;
  workplace_type: string;
  description: string;
  score: number;
  matched_skills: string[];
  posted_date: string;
}

interface GuestPreviewModalProps {
  isOpen: boolean;
  onClose: () => void;
  matches: JobMatch[];
  totalMatches: number;
  parsedData?: {
    name?: string;
    skills_count?: number;
    experience_years?: number;
  };
  onRegister: () => void;
}

export function GuestPreviewModal({
  isOpen,
  onClose,
  matches,
  totalMatches,
  parsedData,
  onRegister
}: GuestPreviewModalProps) {
  const [expandedJob, setExpandedJob] = useState<number | null>(null);

  // Show error state if no matches and no parsed data
  if (totalMatches === 0 && !parsedData) {
    return (
      <Dialog open={isOpen} onOpenChange={onClose}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <DialogTitle className="text-2xl font-bold text-[#1A1A1A] mb-2">
                  We're having trouble analyzing your resume
                </DialogTitle>
                <DialogDescription className="text-lg text-[#4A4A4A]">
                  This could happen if the file format is incompatible or the text isn't readable.
                </DialogDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={onClose}
                className="text-gray-500 hover:text-gray-700"
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          </DialogHeader>

          <div className="space-y-4 mt-6">
            <div className="bg-amber-50 border border-amber-200 rounded-lg p-4">
              <h4 className="font-semibold text-amber-900 mb-2">Try these solutions:</h4>
              <ul className="space-y-2 text-sm text-amber-800">
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-amber-600 mt-1.5 flex-shrink-0"></div>
                  <span>Make sure your resume is in PDF format</span>
                </li>
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-amber-600 mt-1.5 flex-shrink-0"></div>
                  <span>Ensure the PDF contains selectable text (not a scanned image)</span>
                </li>
                <li className="flex items-start gap-2">
                  <div className="w-1.5 h-1.5 rounded-full bg-amber-600 mt-1.5 flex-shrink-0"></div>
                  <span>Try re-exporting your resume as a fresh PDF</span>
                </li>
              </ul>
            </div>

            <div className="flex gap-3">
              <Button
                onClick={onClose}
                className="flex-1 bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white"
              >
                Try Another File
              </Button>
              <Button
                onClick={onRegister}
                variant="outline"
                className="flex-1 border-[#2D5BFF] text-[#2D5BFF] hover:bg-[#F5F8FF]"
              >
                Create Account Anyway
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    );
  }

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent
        className="max-w-4xl w-[calc(100vw-4rem)] sm:w-[90vw] flex flex-col p-0"
        style={{
          maxHeight: '90vh',
          overflow: 'hidden'
        }}
      >
        <div className="p-6 flex-shrink-0 border-b border-gray-200">
          <DialogHeader>
            <div className="flex items-start justify-between">
              <div className="flex-1">
                <DialogTitle className="text-2xl font-bold text-[#1A1A1A] mb-2 flex items-center gap-2">
                  <Sparkles className="h-6 w-6 text-[#2D5BFF]" />
                  {parsedData?.name ? `Great news, ${parsedData.name.split(' ')[0]}!` : 'Great news!'}
                </DialogTitle>
                <DialogDescription className="text-lg text-[#4A4A4A]">
                  We found <span className="font-bold text-[#2D5BFF]">{totalMatches}+ matching opportunities</span> based on your resume.
                  {parsedData?.skills_count && (
                    <> We analyzed your <span className="font-semibold">{parsedData.skills_count} skills</span> and{' '}
                      <span className="font-semibold">{parsedData.experience_years} years of experience</span>.</>
                  )}
                </DialogDescription>
              </div>
              <Button
                variant="ghost"
                size="sm"
                onClick={onClose}
                className="text-gray-500 hover:text-gray-700"
              >
                <X className="h-4 w-4" />
              </Button>
            </div>
          </DialogHeader>
        </div>

        <div className="flex-1 min-h-0 overflow-y-auto px-6 py-6">
          {/* Preview Notice */}
          <div className="bg-gradient-to-r from-[#2D5BFF]/10 to-[#8B5CF6]/10 border-2 border-[#2D5BFF]/30 rounded-lg p-6 mb-6">
          <div className="flex items-start gap-4">
            <Lock className="h-6 w-6 text-[#2D5BFF] mt-0.5 flex-shrink-0" />
            <div className="flex-1">
              <h4 className="font-semibold text-[#1A1A1A] mb-2">Preview Mode</h4>
              <p className="text-sm text-[#4A4A4A] mb-2">
                You're seeing {matches.length} of <span className="font-bold text-[#2D5BFF]">{totalMatches} matching opportunities</span>.
              </p>
              <p className="text-sm text-[#4A4A4A] mb-4">
              Create an account to:
              </p>
              <ul className="text-sm text-[#4A4A4A] space-y-2 mb-4">
                <li className="flex items-start gap-2">
                  <span className="text-[#2D5BFF] font-bold flex-shrink-0">•</span>
                  <span>Access all <span className="font-bold text-[#2D5BFF]">{totalMatches}+</span> opportunities</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-[#2D5BFF] font-bold flex-shrink-0">•</span>
                  <span>See full job descriptions and salary ranges</span>
                </li>
                <li className="flex items-start gap-2">
                  <span className="text-[#2D5BFF] font-bold flex-shrink-0">•</span>
                  <span>Apply with one click using AI-powered automation</span>
                </li>
              </ul>
              <Button
                onClick={onRegister}
                className="w-full bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white font-semibold"
              >
                Create Account
                <ArrowRight className="ml-2 h-4 w-4" />
              </Button>
            </div>
          </div>
        </div>

        {/* Job Matches */}
        <div className="space-y-4 mb-8">
          <h3 className="font-semibold text-[#1A1A1A] text-lg">Your Top Matches</h3>
          {matches.map((job, index) => (
            <Card
              key={job.id}
              className="p-6 hover:shadow-lg transition-shadow border-[#E6E9ED] cursor-pointer"
              onClick={() => setExpandedJob(expandedJob === index ? null : index)}
            >
              {/* Job Header */}
              <div className="flex items-start justify-between mb-3">
                <div className="flex-1">
                  <div className="flex items-center gap-2 mb-2">
                    <h4 className="font-semibold text-[#1A1A1A] text-lg">{job.job_title}</h4>
                    <Badge className="bg-[#28A745] text-white">
                      {Math.round((job.score / 100) * 100)}% Match
                    </Badge>
                  </div>
                  <p className="text-[#4A4A4A] font-medium mb-2">{job.company}</p>
                  <div className="flex flex-wrap items-center gap-3 text-sm text-[#4A4A4A]">
                    <div className="flex items-center gap-1">
                      <MapPin className="h-4 w-4" />
                      <span>{job.location}</span>
                    </div>
                    {job.workplace_type && (
                      <div className="flex items-center gap-1">
                        <Briefcase className="h-4 w-4" />
                        <span className="capitalize">{job.workplace_type}</span>
                      </div>
                    )}
                    {job.posted_date && (
                      <div className="flex items-center gap-1">
                        <TrendingUp className="h-4 w-4" />
                        <span>Posted {new Date(job.posted_date).toLocaleDateString()}</span>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Matched Skills */}
              {job.matched_skills && job.matched_skills.length > 0 && (
                <div className="mb-3">
                  <p className="text-sm text-[#4A4A4A] mb-2">Matching Skills:</p>
                  <div className="flex flex-wrap gap-2">
                    {job.matched_skills.map((skill, skillIndex) => (
                      <Badge key={skillIndex} variant="secondary" className="bg-[#F5F8FF] text-[#2D5BFF]">
                        {skill}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}

              {/* Description Preview (Blurred) */}
              <div className="relative">
                <p className="text-sm text-[#4A4A4A] line-clamp-2">
                  {job.description}
                </p>
                <div className="absolute inset-0 bg-gradient-to-b from-transparent via-white/50 to-white pointer-events-none"></div>
              </div>

              {/* Locked Content Indicator */}
              <div className="mt-3 flex items-center justify-between pt-3 border-t border-[#E6E9ED]">
                <div className="flex items-center gap-2 text-sm text-[#4A4A4A]">
                  <Lock className="h-4 w-4" />
                  <span>Create account to view full details and apply</span>
                </div>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={(e: React.MouseEvent) => {
                    e.stopPropagation();
                    onRegister();
                  }}
                  className="border-[#2D5BFF] text-[#2D5BFF] hover:bg-[#F5F8FF]"
                >
                  Unlock
                </Button>
              </div>
            </Card>
          ))}
        </div>

        {/* Bottom CTA */}
        <div
          className="mt-6 mb-6 p-6 rounded-lg text-center"
          style={{
            background: 'linear-gradient(to bottom right, #2D5BFF, #8B5CF6)',
            color: 'white'
          }}
        >
          <h3 className="text-xl font-bold mb-2" style={{ color: 'white' }}>Ready to unlock {totalMatches}+ opportunities?</h3>
          <p className="mb-4" style={{ color: 'white', opacity: 0.9 }}>
            Join AppliFlow and let AI handle the busywork. Apply to hundreds of matching jobs in minutes, not days.
          </p>
          <div className="flex gap-3 justify-center">
            <Button
              onClick={onRegister}
              className="font-semibold px-8"
              size="lg"
              style={{
                backgroundColor: 'white',
                color: '#2D5BFF',
                border: 'none'
              }}
            >
              Create Account
            </Button>
          </div>
          <p className="mt-3 text-sm" style={{ color: 'white', opacity: 0.8 }}>
            No credit card required • 2-minute setup • Cancel anytime
          </p>
        </div>
      </div>
      </DialogContent>
    </Dialog>
  );
}
