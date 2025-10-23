import { useState } from "react";
import { Button } from "./ui/button";
import { LavaLampBackground } from "./LavaLampBackground";
import { Lock, Shield, Clock } from "lucide-react";

interface GuestUploadData {
  sessionId: string;
  matches: any[];
  totalMatches: number;
  parsedData?: {
    name?: string;
    skills_count?: number;
    experience_years?: number;
  };
}

interface HeroSectionProps {
  onDashboard?: () => void;
  isAuthenticated?: boolean;
  onGuestUploadComplete?: (data: GuestUploadData) => void;
}

export function HeroSection({ onDashboard, isAuthenticated, onGuestUploadComplete }: HeroSectionProps) {
  const [isDragOver, setIsDragOver] = useState(false);
  const [isUploading, setIsUploading] = useState(false);

  // Add keyframe animation for subtle floating effect
  const floatKeyframes = `
    @keyframes float {
      0%, 100% {
        transform: translateY(0px);
      }
      50% {
        transform: translateY(-3px);
      }
    }
  `;

  // Inject keyframes into document
  if (typeof document !== 'undefined' && !document.getElementById('float-animation')) {
    const style = document.createElement('style');
    style.id = 'float-animation';
    style.textContent = floatKeyframes;
    document.head.appendChild(style);
  }

  const handleFileUpload = async (files: FileList) => {
    if (files.length === 0) return;

    setIsUploading(true);
    const formData = new FormData();

    Array.from(files).forEach(file => {
      formData.append('files[]', file);
    });

    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/api/guest/resume-upload', {
        method: 'POST',
        body: formData,
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });

      if (!response.ok) {
        throw new Error('Upload failed');
      }

      const data = await response.json();

      // Add intentional 5-second delay to show "Analyzing Resume" state
      // This improves perceived value (instant feels cheap)
      await new Promise(resolve => setTimeout(resolve, 5000));

      // Store session ID in localStorage
      if (data.session_id) {
        localStorage.setItem('guest_session_id', data.session_id);
      }

      // Trigger preview modal with full data
      if (onGuestUploadComplete && data.session_id) {
        onGuestUploadComplete({
          sessionId: data.session_id,
          matches: data.preview_matches || [],
          totalMatches: data.total_matches || 0,
          parsedData: data.parsed_data || null,
        });
      }
    } catch (error) {
      console.error('Guest upload failed:', error);
      alert('Upload failed. Please try again.');
    } finally {
      setIsUploading(false);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files) {
      handleFileUpload(files);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    const files = e.dataTransfer.files;
    if (files) {
      handleFileUpload(files);
    }
  };

  return (
    <section className="w-full min-h-screen relative overflow-hidden">
      {/* Animated Lava Lamp Background */}
      <LavaLampBackground />

      {/* Gradient Overlay for smooth transition */}
      <div className="absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-white pointer-events-none z-20"
           style={{ background: "linear-gradient(to bottom, transparent 0%, transparent 85%, white 100%)" }}
      />

      <div className="max-w-4xl mx-auto px-6 pt-32 lg:pt-40 pb-40 lg:pb-48 text-center relative z-10">
        <div className="mb-8">
          <img
            src="/images/af-main.png"
            alt="AppliFlow Logo"
            className="h-auto mx-auto mb-1"
            style={{ maxWidth: '75%' }}
          />
        </div>

        <h1 className="text-white mb-6 max-w-4xl mx-auto">
          Your AI job search copilot for tech professionals.
        </h1>

        <p className="text-lg text-white/90 mb-8 max-w-2xl mx-auto">
          Find the best tech jobs, cut the busywork, and apply with confidence.
        </p>

        {isAuthenticated ? (
          <Button
            className="bg-white text-[#6366f1] hover:bg-white/90 px-8 py-3 font-semibold rounded-lg border-0 transition-all duration-300 shadow-lg btn-glow-white"
            size="lg"
            onClick={onDashboard}
          >
            Go to Dashboard
          </Button>
        ) : (
          <>
            {/* Guest Upload Area */}
            <div className="max-w-xl mx-auto mb-6">
              <div
                className={`
                  relative border-2 border-dashed rounded-xl p-8
                  transition-all duration-300 cursor-pointer
                  ${isDragOver
                    ? 'border-white bg-white/20 scale-[1.02]'
                    : 'border-white/40 bg-white/10 hover:border-white/60 hover:bg-white/15'
                  }
                `}
                onDragOver={(e) => { e.preventDefault(); setIsDragOver(true); }}
                onDragLeave={(e) => { e.preventDefault(); setIsDragOver(false); }}
                onDrop={handleDrop}
                onClick={() => document.getElementById('hero-file-input')?.click()}
              >
                {isUploading ? (
                  <div className="flex flex-col items-center gap-3">
                    <div className="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent"></div>
                    <p className="text-white font-medium">Analyzing your resume...</p>
                  </div>
                ) : (
                  <div className="flex flex-col items-center gap-3">
                    <div
                      className="p-3 bg-white/20 rounded-full backdrop-blur-sm"
                      style={{
                        animation: 'float 3s ease-in-out infinite'
                      }}
                    >
                      <svg className="h-8 w-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                      </svg>
                    </div>
                    <div>
                      <p className="text-white font-semibold text-lg mb-1">
                        Find jobs that match your resume instantly
                      </p>
                      <p className="text-white text-sm">
                        Drop your resume to see what roles fit you best.
                      </p>
                    </div>
                  </div>
                )}
              </div>

              <input
                id="hero-file-input"
                type="file"
                className="hidden"
                accept=".pdf,.doc,.docx"
                onChange={handleFileSelect}
              />
            </div>

            {/* Trust Signals */}
            <div className="flex items-center justify-center gap-6 text-white/90 text-sm">
              <div className="flex items-center gap-2">
                <Lock className="h-4 w-4" />
                <span>Encrypted & Private</span>
              </div>
              <div className="flex items-center gap-2">
                <Shield className="h-4 w-4" />
                <span>No Spam</span>
              </div>
              <div className="flex items-center gap-2">
                <Clock className="h-4 w-4" />
                <span>2-Minute Setup</span>
              </div>
            </div>
          </>
        )}
      </div>
    </section>
  );
}