import { useState } from "react";
import { Eye, EyeOff, Mail, Lock, ArrowRight, Key } from "lucide-react";
import { Button } from "./ui/button";
import { Input } from "./ui/input";
import { Label } from "./ui/label";
import { Card } from "./ui/card";
import { Separator } from "./ui/separator";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

interface LoginProps {
  onLogin: () => void;
  onSwitchToRegister?: () => void;
  isPrelaunch?: boolean;
}

export function Login({ onLogin, onSwitchToRegister, isPrelaunch = false }: LoginProps) {
  const { ref, isVisible } = useScrollAnimation(0.3);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [betaToken, setBetaToken] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [isBetaLoading, setIsBetaLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

const handleSubmit = async (e: React.FormEvent) => {
  e.preventDefault();
  setIsLoading(true);
  setError(null);
  
  try {
    // Get CSRF token from Laravel
    const tokenResponse = await fetch('/api/csrf-token', {
      credentials: 'include',
    });
    const { token } = await tokenResponse.json();
    const response = await fetch('/api/login', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ email, password }),
    });

    const data = await response.json();

    if (response.ok) {
      onLogin();
    } else {
      setError(data.message || 'Invalid credentials. Please try again.');
    }
  } catch (error) {
    console.error('Login error:', error);
    setError('An error occurred. Please try again.');
  } finally {
    setIsLoading(false);
  }
};

  const handleBetaTokenSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsBetaLoading(true);
    setError(null);

    try {
      // Get CSRF token
      const tokenResponse = await fetch('/api/csrf-token', {
        credentials: 'include',
      });
      const { token: csrfToken } = await tokenResponse.json();

      const response = await fetch('/api/beta/activate', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ token: betaToken.trim() }),
      });

      const data = await response.json();

      if (response.ok) {
        onLogin();
      } else {
        setError(data.message || 'Invalid access code. Please try again.');
      }
    } catch (err) {
      setError('Something went wrong. Please try again.');
    } finally {
      setIsBetaLoading(false);
    }
  };

  const handleGoogleLogin = () => {
    // Redirect to Google OAuth endpoint
    window.location.href = '/auth/google';
  };

  const handleGithubLogin = () => {
    // Handle GitHub OAuth login
  };

  return (
    <div ref={ref} className={`flex-1 flex items-center justify-center p-8 fade-in ${isVisible ? 'visible' : ''}`}>
      <div className="w-full max-w-md">
        {/* Login Card */}
        <Card className={`p-8 shadow-xl border-0 bg-card/95 backdrop-blur-sm fade-in fade-in-delay-1 ${isVisible ? 'visible' : ''}`}>
          <div className="space-y-6">
            {/* Header */}
            <div className={`text-center space-y-2 fade-in fade-in-delay-2 ${isVisible ? 'visible' : ''}`}>
              <h1 className="text-2xl font-bold login-heading-text">Welcome Back</h1>
              <p className="login-text">
                Sign in to your account to continue
              </p>
            </div>

            {/* Error Message */}
            {error && (
              <div className="p-4 bg-red-50 border border-red-200 rounded-lg">
                <p className="text-sm text-red-600">{error}</p>
              </div>
            )}

            {/* Beta Token Login - Show during prelaunch */}
            {isPrelaunch && (
              <>
                <form onSubmit={handleBetaTokenSubmit} className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="betaToken" className="login-text font-medium">Beta Access Token</Label>
                    <div className="relative">
                      <Key className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                      <Input
                        id="betaToken"
                        type="text"
                        placeholder="Enter your 32-character access code"
                        value={betaToken}
                        onChange={(e) => setBetaToken(e.target.value)}
                        className="pl-10 h-12 bg-input-background border-border focus:border-primary focus:ring-primary/20 font-mono text-sm"
                        maxLength={32}
                        required
                      />
                    </div>
                    <p className="text-xs text-gray-500">
                      Use the access code from your welcome email
                    </p>
                  </div>

                  <Button
                    type="submit"
                    disabled={isBetaLoading || betaToken.trim().length !== 32}
                    className="w-full h-12 bg-primary hover:bg-primary/90 text-primary-foreground shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]"
                  >
                    {isBetaLoading ? (
                      <div className="flex items-center space-x-2">
                        <div className="animate-spin rounded-full h-4 w-4 border-2 border-primary-foreground border-t-transparent"></div>
                        <span>Logging in...</span>
                      </div>
                    ) : (
                      <div className="flex items-center space-x-2">
                        <span>Login with Access Token</span>
                        <ArrowRight className="h-4 w-4" />
                      </div>
                    )}
                  </Button>
                </form>

                {/* Divider */}
                <div className="relative">
                  <Separator />
                  <div className="absolute inset-0 flex items-center justify-center">
                    <span className="bg-card px-4 text-sm login-text font-medium">or use password</span>
                  </div>
                </div>
              </>
            )}

            {/* Social Login Buttons */}
            <div className={`space-y-3 fade-in fade-in-delay-3 ${isVisible ? 'visible' : ''}`}>
              <Button
                type="button"
                variant="outline"
                className="w-full h-12 border-border hover:bg-primary/5 hover:border-primary/50 transition-all login-text"
                onClick={handleGoogleLogin}
              >
                <svg className="mr-3 h-5 w-5" viewBox="0 0 24 24">
                  <path
                    fill="currentColor"
                    d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                  />
                  <path
                    fill="currentColor"
                    d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                  />
                  <path
                    fill="currentColor"
                    d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                  />
                  <path
                    fill="currentColor"
                    d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                  />
                </svg>
                Continue with Google
              </Button>

              <Button
                type="button"
                variant="outline"
                className="w-full h-12 border-border hover:bg-primary/5 hover:border-primary/50 transition-all login-text"
                onClick={handleGithubLogin}
              >
                <svg className="mr-3 h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                Continue with GitHub
              </Button>
            </div>

            {/* Divider */}
            <div className="relative">
              <Separator />
              <div className="absolute inset-0 flex items-center justify-center">
                <span className="bg-card px-4 text-sm login-text font-medium">or</span>
              </div>
            </div>

            {/* Login Form */}
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="email" className="login-text font-medium">Email</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="Enter your email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-10 h-12 bg-input-background border-border focus:border-primary focus:ring-primary/20"
                    required
                  />
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="password" className="login-text font-medium">Password</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                  <Input
                    id="password"
                    type={showPassword ? "text" : "password"}
                    placeholder="Enter your password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="pl-10 pr-10 h-12 bg-input-background border-border focus:border-primary focus:ring-primary/20"
                    required
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                  >
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
              </div>

              <div className="flex items-center justify-between text-sm">
                <label className="flex items-center space-x-2 cursor-pointer">
                  <input 
                    type="checkbox" 
                    className="rounded border-border text-primary focus:ring-primary/20"
                  />
                  <span className="login-text font-medium">Remember me</span>
                </label>
                <button
                  type="button"
                  className="text-primary hover:text-primary/80 transition-colors"
                >
                  Forgot password?
                </button>
              </div>

              <Button
                type="submit"
                disabled={isLoading}
                className="w-full h-12 bg-primary hover:bg-primary/90 text-primary-foreground shadow-lg hover:shadow-xl transition-all duration-200 transform hover:scale-[1.02]"
              >
                {isLoading ? (
                  <div className="flex items-center space-x-2">
                    <div className="animate-spin rounded-full h-4 w-4 border-2 border-primary-foreground border-t-transparent"></div>
                    <span>Signing in...</span>
                  </div>
                ) : (
                  <div className="flex items-center space-x-2">
                    <span>Sign In</span>
                    <ArrowRight className="h-4 w-4" />
                  </div>
                )}
              </Button>
            </form>

            {/* Footer */}
            {onSwitchToRegister && !isPrelaunch && (
              <div className="text-center text-sm login-text">
                Don't have an account?{" "}
                <button
                  onClick={onSwitchToRegister}
                  className="signup-link-btn hover:opacity-80 transition-colors font-medium"
                >
                  Sign up
                </button>
              </div>
            )}
          </div>
        </Card>
      </div>
    </div>
  );
}