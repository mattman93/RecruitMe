import { useState, useEffect } from "react";
import { Navbar } from "./components/Navbar";
import { HeroSection } from "./components/HeroSection";
import { FeaturesSection } from "./components/FeaturesSection";
import { TestimonialsSection } from "./components/TestimonialsSection";
import { FileUpload } from "./components/FileUpload";
import { Login } from "./components/Login";
import { Register } from "./components/Register";
import { Dashboard } from "./components/Dashboard";
import { Footer } from "./components/Footer";
import { ValuePreview } from "./components/ValuePreview";
import { ROICalculator } from "./components/ROICalculator";
import { ClosingCTA } from "./components/ClosingCTA";
import { CoreBenefits } from "./components/CoreBenefits";
import { HowItWorks } from "./components/HowItWorks";
import { SocialProof } from "./components/SocialProof";
import { Enterprise } from "./components/Enterprise";
import { ContactUs } from "./components/ContactUs";
import { DataIngestionStats } from "./components/DataIngestionStats";
import { PrivacyPolicy } from "./components/PrivacyPolicy";
import { TermsOfService } from "./components/TermsOfService";
import { Subscribe } from "./components/Subscribe";
import { SubscribeSuccess } from "./components/SubscribeSuccess";
import { GuestPreviewModal } from "./components/GuestPreviewModal";

type AppState = 'loading' | 'guest' | 'login' | 'register' | 'authenticated' | 'upload' | 'enterprise' | 'contact-us' | 'admin-data-ingestion' | 'privacy' | 'terms' | 'subscribe' | 'subscribe-success';

interface GuestPreviewData {
  sessionId: string;
  matches: any[];
  totalMatches: number;
  parsedData?: {
    name?: string;
    email?: string;
    skills_count?: number;
    experience_years?: number;
  };
}

export default function App() {
  const [appState, setAppState] = useState<AppState>('loading');
  const [isUserAuthenticated, setIsUserAuthenticated] = useState<boolean>(false);
  const [isPrelaunch, setIsPrelaunch] = useState<boolean>(false);
  const [hasBetaAccess, setHasBetaAccess] = useState<boolean>(false);
  const [guestPreviewData, setGuestPreviewData] = useState<GuestPreviewData | null>(null);
  const [showGuestPreview, setShowGuestPreview] = useState(false);
  const [registerInitialValues, setRegisterInitialValues] = useState<{name?: string; email?: string}>({});

  // Check authentication status only on initial app load
  useEffect(() => {
    if (appState === 'loading') {
      checkAuthStatus();
    }
  }, []);


  const checkBetaAccess = async () => {
    try {
      const response = await fetch('/api/beta/check', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await response.json();
      setHasBetaAccess(data.activated || false);
    } catch (error) {
      setHasBetaAccess(false);
    }
  };

  const checkAuthStatus = async () => {
    try {
      const response = await fetch('/api/auth/check', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await response.json();

      // Set prelaunch mode
      setIsPrelaunch(data.prelaunch || false);

      // Check beta access status
      await checkBetaAccess();

      if (data.authenticated) {
        setIsUserAuthenticated(true);
        // Check if we're on the dashboard route
        if (window.location.pathname === '/dashboard') {
          setAppState('authenticated');
        } else if (window.location.pathname === '/subscribe') {
          setAppState('subscribe');
        } else if (window.location.pathname === '/subscribe/success') {
          setAppState('subscribe-success');
        } else if (window.location.pathname === '/enterprise') {
          setAppState('enterprise');
        } else if (window.location.pathname === '/admin/data-ingestion') {
          setAppState('admin-data-ingestion');
        } else if (window.location.pathname === '/privacy') {
          setAppState('privacy');
        } else if (window.location.pathname === '/terms') {
          setAppState('terms');
        } else {
          setAppState('guest');
        }
      } else {
        setIsUserAuthenticated(false);
        // Check if we're on the enterprise route
        if (window.location.pathname === '/enterprise') {
          setAppState('enterprise');
        } else if (window.location.pathname === '/privacy') {
          setAppState('privacy');
        } else if (window.location.pathname === '/terms') {
          setAppState('terms');
        } else if (window.location.pathname === '/subscribe') {
          setAppState('subscribe');
        } else if (window.location.pathname === '/subscribe/success') {
          setAppState('subscribe-success');
        } else {
          setAppState('guest');
        }
      }
    } catch (error) {
      console.error('Auth check failed:', error);
      setIsUserAuthenticated(false);
      setAppState('guest');
    }
  };

  const handleSwitchToRegister = () => {
    // Check if user has paid (has stripe session in localStorage)
    const stripeSessionId = localStorage.getItem('stripe_session_id');

    // Allow registration if user has paid, even in prelaunch mode
    if (stripeSessionId || !isPrelaunch) {
      setAppState('register');
    } else {
      setAppState('contact-us');
    }
  };

  const handleSwitchToLogin = () => {
    setAppState('login');
  };

  const handleRegister = async () => {
    setIsUserAuthenticated(true);

    // Check if user has a resume (from guest upload transfer)
    try {
      const response = await fetch('/api/user/has-resume', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await response.json();

      if (data.has_resume) {
        // User has resume (from guest upload), go to dashboard
        window.location.href = '/dashboard';
      } else {
        // User needs to upload resume first
        setAppState('upload');
      }
    } catch (error) {
      console.error('Failed to check resume status:', error);
      // Default to upload on error
      setAppState('upload');
    }
  };

  const handleLogin = async () => {
    setIsUserAuthenticated(true);

    // Check if user has a resume
    try {
      const response = await fetch('/api/user/has-resume', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await response.json();

      if (data.has_resume) {
        // User has resume, go to dashboard
        window.location.href = '/dashboard';
      } else {
        // User needs to upload resume first
        setAppState('upload');
      }
    } catch (error) {
      console.error('Failed to check resume status:', error);
      // Default to upload page on error
      setAppState('upload');
    }
  };

  const handleLogout = async () => {
    try {
      // Get CSRF token from API
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/api/logout', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });

      if (response.ok) {
        await response.json();
        setIsUserAuthenticated(false);
        setAppState('guest');
      } else {
        console.error('Logout failed');
        setIsUserAuthenticated(false);
        setAppState('guest');
      }
    } catch (error) {
      console.error('Logout error:', error);
      setIsUserAuthenticated(false);
      setAppState('guest');
    }
  };

  const handleSeeMatches = () => {
    if (isPrelaunch && !hasBetaAccess) {
      setAppState('contact-us');
    } else {
      setAppState('login');
    }
  };

  const handleGoHome = () => {
    window.location.href = '/';
  };

  const handleGoDashboard = () => {
    window.location.href = '/dashboard';
  };

  const handleGoEnterprise = () => {
    window.location.href = '/enterprise';
  };

  const handleGoPricing = () => {
    window.location.href = '/subscribe';
  };

  const handleShowLogin = () => {
    if (isPrelaunch && !hasBetaAccess) {
      setAppState('contact-us');
    } else {
      setAppState('login');
    }
  };

  const handleGuestUploadComplete = (data: GuestPreviewData) => {
    // Data is already provided from upload response
    setGuestPreviewData(data);
    setShowGuestPreview(true);
  };

  const handleGuestRegister = () => {
    setShowGuestPreview(false);

    // Extract name and email from guest preview data to autofill registration
    if (guestPreviewData?.parsedData) {
      setRegisterInitialValues({
        name: guestPreviewData.parsedData.name || '',
        email: guestPreviewData.parsedData.email || ''
      });
    }

    handleSwitchToRegister();
  };

  // Loading state
  if (appState === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white">
        <div className="flex items-center space-x-2">
          <div className="animate-spin rounded-full h-8 w-8 border-2 border-primary border-t-transparent"></div>
          <span className="text-primary">Loading...</span>
        </div>
      </div>
    );
  }

  // Login state (from guest page)
  if (appState === 'login') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <div className="flex-1 flex items-center justify-center p-8">
          <div className="w-full max-w-md space-y-6">
            <div className="text-center">
              <button
                onClick={() => setAppState('guest')}
                className="text-sm text-white back-to-upload-btn hover:text-primary transition-colors mb-4"
              >
                ← Back to home
              </button>
            </div>
            <Login onLogin={handleLogin} onSwitchToRegister={handleSwitchToRegister} isPrelaunch={isPrelaunch} />
          </div>
        </div>
      </div>
    );
  }

  // Register state (not available during prelaunch)
  if (appState === 'register') {
    // Redirect to contact-us during prelaunch
    if (isPrelaunch) {
      return (
        <ContactUs
          onClose={() => setAppState('guest')}
          onBetaAccessSuccess={async () => {
            await checkBetaAccess();
            try {
              const response = await fetch('/api/user/has-resume', {
                credentials: 'include',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
              });
              const data = await response.json();
              if (data.has_resume) {
                window.location.href = '/dashboard';
              } else {
                setAppState('upload');
              }
            } catch (error) {
              console.error('Failed to check resume status:', error);
              setAppState('upload');
            }
          }}
        />
      );
    }

    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <Register onRegister={handleRegister} onSwitchToLogin={handleSwitchToLogin} initialValues={registerInitialValues} />
      </div>
    );
  }

  // Upload state (file upload page - shown after registration)
  if (appState === 'upload') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <div className="flex-1 flex items-center justify-center p-8">
          <div className="w-full">
            <div className="text-center mb-8">
              <h2 className="text-2xl font-semibold mb-4" style={{ color: '#1A1A1A' }}>
                Upload Your Resume
              </h2>
              <p style={{ color: '#4A4A4A' }}>
                Get personalized job matches based on your skills and experience
              </p>
            </div>
            <FileUpload
              onUploadSuccess={() => window.location.href = '/dashboard'}
              isAuthenticated={isUserAuthenticated}
            />
          </div>
        </div>
      </div>
    );
  }

  // Guest state (not authenticated)
  // Landing page (guest or authenticated)
  if (appState === 'guest') {
      return (
        <div className="min-h-screen bg-white">
          <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
          <HeroSection
            onDashboard={handleGoDashboard}
            isAuthenticated={isUserAuthenticated}
            onGuestUploadComplete={handleGuestUploadComplete}
          />
          <ROICalculator />
          <ValuePreview />
          <CoreBenefits />
          <HowItWorks />
          <SocialProof />
          <ClosingCTA onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={isUserAuthenticated} />
          <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />

          {/* Guest Preview Modal */}
          {guestPreviewData && (
            <GuestPreviewModal
              isOpen={showGuestPreview}
              onClose={() => setShowGuestPreview(false)}
              matches={guestPreviewData.matches}
              totalMatches={guestPreviewData.totalMatches}
              parsedData={guestPreviewData.parsedData}
              onRegister={handleGuestRegister}
            />
          )}
        </div>
      );
  }

  // Enterprise state
    if (appState === 'enterprise') {
    return (
      <div className="min-h-screen bg-white">
        <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <Enterprise />
        <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />
      </div>
    );
  }

  // Contact Us state (prelaunch mode)
  if (appState === 'contact-us') {
    return (
      <ContactUs
        onClose={() => setAppState('guest')}
        onBetaAccessSuccess={async () => {
          // Refresh beta access status
          await checkBetaAccess();

          // Check if user has a resume (they're now logged in)
          try {
            const response = await fetch('/api/user/has-resume', {
              credentials: 'include',
              headers: {
                'X-Requested-With': 'XMLHttpRequest',
              },
            });
            const data = await response.json();

            if (data.has_resume) {
              // User has resume, redirect to dashboard
              window.location.href = '/dashboard';
            } else {
              // User needs to upload resume first
              setAppState('upload');
            }
          } catch (error) {
            console.error('Failed to check resume status:', error);
            // Default to upload page on error
            setAppState('upload');
          }
        }}
      />
    );
  }

  // Admin Data Ingestion state
  if (appState === 'admin-data-ingestion') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={true} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <DataIngestionStats />
      </div>
    );
  }

  // Privacy Policy state
  if (appState === 'privacy') {
    return (
      <div className="min-h-screen bg-white">
        <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <PrivacyPolicy />
        <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />
      </div>
    );
  }

  // Terms of Service state
  if (appState === 'terms') {
    return (
      <div className="min-h-screen bg-white">
        <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
        <TermsOfService />
        <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />
      </div>
    );
  }

  // Subscribe state
  if (appState === 'subscribe') {
    console.log('App.tsx - rendering Subscribe, isUserAuthenticated:', isUserAuthenticated);
    return (
      <Subscribe
        onBack={isUserAuthenticated ? handleGoDashboard : handleGoHome}
        isAuthenticated={isUserAuthenticated}
      />
    );
  }

  // Subscribe Success state
  if (appState === 'subscribe-success') {
    return (
      <SubscribeSuccess
        onReturnToDashboard={handleGoDashboard}
        onRegister={handleSwitchToRegister}
        isAuthenticated={isUserAuthenticated}
      />
    );
  }

  // Authenticated state
  return (
    <div className="min-h-screen flex flex-col bg-white">
      <Navbar isAuthenticated={true} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} onPricing={handleGoPricing} />
      <Dashboard />
    </div>
  );
}