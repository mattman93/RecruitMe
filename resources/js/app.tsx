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

type AppState = 'loading' | 'guest' | 'login' | 'auth-required' | 'register' | 'authenticated' | 'upload' | 'home';

export default function App() {
  const [appState, setAppState] = useState<AppState>('loading');
  const [isUserAuthenticated, setIsUserAuthenticated] = useState<boolean>(false);

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
      
      if (data.authenticated) {
        setIsUserAuthenticated(true);
        setAppState('authenticated');
      } else {
        setIsUserAuthenticated(false);
        setAppState('guest');
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
    setAppState('upload');
  };

  const handleGoHome = () => {
    setAppState('home');
  };

  const handleGoDashboard = () => {
    setAppState('authenticated');
  };

  const handleShowLogin = () => {
    setAppState('login');
  };

  const handleForceLogout = async () => {
    // Force logout first, then show login
    try {
      // Get CSRF token from API
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token } = await tokenResponse.json();

      await fetch('/api/logout', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': token,
        },
      });
    } catch (error) {
      console.error('Logout error:', error);
    }
    setAppState('login');
  };

  // Loading state
  if (appState === 'loading') {
    return (
      <div className="min-h-screen flex items-center justify-center navy-gradient">
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
      <div className="min-h-screen flex flex-col navy-gradient">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
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
      <div className="min-h-screen flex flex-col navy-gradient">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
        <Register onRegister={handleRegister} onSwitchToLogin={handleSwitchToLogin} />
      </div>
    );
  }

  // Login required state (after file upload)
  if (appState === 'auth-required') {
    return (
      <div className="min-h-screen flex flex-col navy-gradient">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
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
      <div className="min-h-screen flex flex-col navy-gradient">
        <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
        <div className="flex-1 flex items-center justify-center p-8">
          <div className="w-full max-w-2xl">
            <div className="text-center mb-8">
              <button 
                onClick={() => setAppState('guest')}
                className="text-sm text-white back-to-upload-btn hover:text-primary transition-colors mb-4"
              >
                ← Back to home
              </button>
              <h2 className="text-2xl font-semibold text-white mb-4">
                Upload Your Resume
              </h2>
              <p className="text-white/90">
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
        <Footer />
      </div>
    );
  }

  // Guest state (not authenticated)
  if (appState === 'guest') {
      return (
        <div className="min-h-screen bg-white">
          <Navbar isAuthenticated={false} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
          <HeroSection onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={false} />
          <ValuePreview />
          <CoreBenefits />
          <HowItWorks />
          <SocialProof />
          <ClosingCTA onSeeMatches={handleSeeMatches} onDashboard={handleGoDashboard} isAuthenticated={false} />
          <Footer />
        </div>
      );
  }

  // Authenticated state
  return (
    <div className="min-h-screen flex flex-col navy-gradient">
      <Navbar isAuthenticated={true} onLogout={handleLogout} onLogin={handleShowLogin} onHome={handleGoHome} onDashboard={handleGoDashboard} />
      <Dashboard />
    </div>
  );
}