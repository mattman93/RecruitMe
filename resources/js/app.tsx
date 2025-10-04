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
import { ClosingCTA } from "./components/ClosingCTA";
import { CoreBenefits } from "./components/CoreBenefits";
import { HowItWorks } from "./components/HowItWorks";
import { SocialProof } from "./components/SocialProof";
import { Enterprise } from "./components/Enterprise";
import { ContactUs } from "./components/ContactUs";
import { DataIngestionStats } from "./components/DataIngestionStats";

type AppState = 'loading' | 'guest' | 'login' | 'auth-required' | 'register' | 'authenticated' | 'upload' | 'home' | 'enterprise' | 'contact-us' | 'admin-data-ingestion';

export default function App() {
  const [appState, setAppState] = useState<AppState>('loading');
  const [isUserAuthenticated, setIsUserAuthenticated] = useState<boolean>(false);
  const [isPrelaunch, setIsPrelaunch] = useState<boolean>(false);

  // Check authentication status only on initial app load
  useEffect(() => {
    if (appState === 'loading') {
      checkAuthStatus();
    }
  }, []);


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

      if (data.authenticated) {
        setIsUserAuthenticated(true);
        // Check if we're on the dashboard route
        if (window.location.pathname === '/dashboard') {
          setAppState('authenticated');
        } else if (window.location.pathname === '/enterprise') {
          setAppState('enterprise');
        } else if (window.location.pathname === '/admin/data-ingestion') {
          setAppState('admin-data-ingestion');
        } else {
          setAppState('home');
        }
      } else {
        setIsUserAuthenticated(false);
        // Check if we're on the enterprise route
        if (window.location.pathname === '/enterprise') {
          setAppState('enterprise');
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
    setAppState('register');
  };

  const handleSwitchToLogin = () => {
    setAppState('auth-required');
  };

  const handleRegister = async () => {
    // After successful registration, check for any pending guest uploads
    try {
      // Get CSRF token from API
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/api/user/claim-guest-uploads', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });
      
      if (response.ok) {
        console.log('Guest uploads claimed successfully');
      }
    } catch (error) {
      console.error('Failed to claim guest uploads:', error);
    }
    
    setIsUserAuthenticated(true);
    setAppState('authenticated');
  };

  const handleLogin = async () => {
    // After successful login, check for any pending guest uploads
    try {
      // Get CSRF token from API
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      const response = await fetch('/api/user/claim-guest-uploads', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });
      
      if (response.ok) {
        console.log('Guest uploads claimed successfully');
      }
    } catch (error) {
      console.error('Failed to claim guest uploads:', error);
    }
    
    setIsUserAuthenticated(true);
    setAppState('authenticated');
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
        const data = await response.json();

        // Log if user had Google OAuth
        if (data.had_google_oauth) {
          console.log('User with Google OAuth logged out');
        }

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

  const handleAuthRequired = () => {
    setAppState('auth-required');
  };

  const handleSeeMatches = () => {
    if (isPrelaunch) {
      setAppState('contact-us');
    } else {
      setAppState('upload');
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

  const handleShowLogin = () => {
    if (isPrelaunch) {
      setAppState('contact-us');
    } else {
      setAppState('login');
    }
  };

  const handleForceLogout = async () => {
    // Force logout first, then show login
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
        const data = await response.json();

        // Log if user had Google OAuth
        if (data.had_google_oauth) {
          console.log('User with Google OAuth logged out');
        }
      }
    } catch (error) {
      console.error('Logout error:', error);
    }
    setAppState('login');
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
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
        <div className="flex-1 flex items-center justify-center p-8">
          <div className="w-full max-w-md space-y-6">
            <div className="text-center">
              <button 
                onClick={() => setAppState('guest')}
                className="text-sm text-white back-to-upload-btn hover:text-primary transition-colors mb-4"
              >
                ← Back to upload
              </button>
            </div>
            <Login onLogin={handleLogin} onSwitchToRegister={handleSwitchToRegister} />
          </div>
        </div>
      </div>
    );
  }

  // Register state
  if (appState === 'register') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
        <Register onRegister={handleRegister} onSwitchToLogin={handleSwitchToLogin} />
      </div>
    );
  }

  // Login required state (after file upload)
  if (appState === 'auth-required') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
        <div className="flex-1 flex items-center justify-center p-8">
          <div className="text-center space-y-4 mb-8">
            <h2 className="text-2xl font-semibold text-white">
              Almost There!
            </h2>
            <p className="text-white/90 max-w-md">
              Your resume has been uploaded successfully. Please sign in to start finding jobs and applying to positions.
            </p>
          </div>
        </div>
        <Login onLogin={handleLogin} onSwitchToRegister={handleSwitchToRegister} />
      </div>
    );
  }

  // Upload state (file upload page)
  if (appState === 'upload') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
        <div className="flex-1 flex items-center justify-center p-8">
          <div className="w-full max-w-2xl">
            <div className="text-center mb-8">
              <button 
                onClick={() => setAppState('guest')}
                className="text-sm back-to-upload-btn hover:text-primary transition-colors mb-4"
                style={{ color: '#4b38f1' }}
              >
                ← Back to home
              </button>
              <h2 className="text-2xl font-semibold mb-4" style={{ color: '#1A1A1A' }}>
                Upload Your Resume
              </h2>
              <p style={{ color: '#4A4A4A' }}>
                Get personalized job matches based on your skills and experience
              </p>
            </div>
            <FileUpload 
              onAuthRequired={handleAuthRequired} 
              onShowLogin={handleShowLogin}
              isAuthenticated={false}
            />
          </div>
        </div>
      </div>
    );
  }

  // Home state (uses global authentication state)
  if (appState === 'home') {
    return (
      <div className="min-h-screen bg-white">
        <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
        <HeroSection onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={isUserAuthenticated} />
        <ValuePreview />
        <CoreBenefits />
        <HowItWorks />
        <SocialProof />
        <ClosingCTA onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={isUserAuthenticated} />
        <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />
      </div>
    );
  }

  // Guest state (not authenticated)
  if (appState === 'guest') {
      return (
        <div className="min-h-screen bg-white">
          <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
          <HeroSection onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={false} />
          <ValuePreview />
          <CoreBenefits />
          <HowItWorks />
          <SocialProof />
          <ClosingCTA onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={false} />
          <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />
        </div>
      );
  }

  // Enterprise state
    if (appState === 'enterprise') {
    return (
      <div className="min-h-screen bg-white">
        <Navbar isAuthenticated={isUserAuthenticated} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
        <Enterprise />
        <Footer onContactUs={() => setAppState('contact-us')} isPrelaunch={isPrelaunch} />
      </div>
    );
  }

  // Contact Us state (prelaunch mode)
  if (appState === 'contact-us') {
    return (
      <ContactUs onClose={() => setAppState('guest')} />
    );
  }

  // Admin Data Ingestion state
  if (appState === 'admin-data-ingestion') {
    return (
      <div className="min-h-screen flex flex-col bg-white">
        <Navbar isAuthenticated={true} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
        <DataIngestionStats />
      </div>
    );
  }

  // Authenticated state
  return (
    <div className="min-h-screen flex flex-col bg-white">
      <Navbar isAuthenticated={true} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} onEnterprise={handleGoEnterprise} />
      <Dashboard />
    </div>
  );
}