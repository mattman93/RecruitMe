import { useState, useEffect } from "react";
import { Button } from "./ui/button";
import { ImageWithFallback } from "./figma/ImageWithFallback";
import { Menu, Shield } from "lucide-react";

interface NavbarProps {
  isAuthenticated?: boolean;
  onLogout?: () => void;
  onLogin?: () => void;
  onHome?: () => void;
  onDashboard?: () => void;
  onEnterprise?: () => void;
  onPricing?: () => void;
}

export function Navbar({ isAuthenticated, onLogout, onLogin, onHome, onDashboard, onEnterprise, onPricing }: NavbarProps) {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const [isSuperAdmin, setIsSuperAdmin] = useState(false);

  // Check if user is super admin
  useEffect(() => {
    if (isAuthenticated) {
      checkAdminStatus();
    }
  }, [isAuthenticated]);

  const checkAdminStatus = async () => {
    try {
      const response = await fetch('/api/auth/check', {
        credentials: 'include',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const data = await response.json();
      setIsSuperAdmin(data.user?.user_role === 2);
    } catch (error) {
      console.error('Failed to check admin status:', error);
      setIsSuperAdmin(false);
    }
  };

  const handleAdminDashboard = () => {
    window.location.href = '/admin/data-ingestion';
  };
  return (
    <nav className="w-full bg-white border-b border-[#E6E9ED] px-6 py-4">
      <div className="flex items-center justify-between">
        {/* Logo */}
        <button onClick={onHome} className="flex items-center">
          <div className="w-10 h-10 bg-[#2D5BFF] rounded-lg flex items-center justify-center hover:bg-[#1E3FCC] transition-colors">
            <span className="text-white font-bold text-lg mono">AF</span>
          </div>
        </button>

        {/* Right side navigation */}
        <div className="flex items-center gap-4">
          <button
            onClick={onPricing}
            className="text-[#4A4A4A] hover:text-[#1A1A1A] transition-colors"
          >
            Pricing
          </button>

          {!isAuthenticated ? (
            <>
              <button
                onClick={onLogin}
                className="text-[#4A4A4A] hover:text-[#1A1A1A] transition-colors"
              >
                Login
              </button>

              <Button
                onClick={onEnterprise}
                className="bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white px-6 py-2"
              >
                AppliFlow Enterprise
              </Button>
            </>
          ) : (
            <>
              <button
                onClick={onDashboard}
                className="text-[#4A4A4A] hover:text-[#1A1A1A] transition-colors"
              >
                Dashboard
              </button>

              <Button
                onClick={onLogout}
                variant="default"
                className="logout-button"
              >
                Logout
              </Button>
            </>
          )}

          <div className="relative">
            <Button
              variant="ghost"
              size="icon"
              className="p-2 hover:bg-[#F5F8FF]"
              onClick={() => setIsMenuOpen(!isMenuOpen)}
            >
              <Menu className="h-5 w-5 text-[#4A4A4A]" />
            </Button>

            {/* Dropdown Menu */}
            {isMenuOpen && (
              <>
                {/* Backdrop */}
                <div
                  className="fixed inset-0 z-10"
                  onClick={() => setIsMenuOpen(false)}
                />

                {/* Menu */}
                <div className="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-20">
                  {isAuthenticated && isSuperAdmin && (
                    <>
                      <button
                        onClick={() => {
                          handleAdminDashboard();
                          setIsMenuOpen(false);
                        }}
                        className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                      >
                        <Shield className="h-4 w-4 text-purple-600" />
                        admin
                      </button>
                      <div className="border-t border-gray-200 my-2" />
                    </>
                  )}

                  <button
                    onClick={() => {
                      onPricing?.();
                      setIsMenuOpen(false);
                    }}
                    className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                  >
                    pricing
                  </button>

                  {isAuthenticated ? (
                    <>
                      <button
                        onClick={() => {
                          onDashboard?.();
                          setIsMenuOpen(false);
                        }}
                        className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                      >
                        dashboard
                      </button>
                      <button
                        onClick={() => {
                          onHome?.();
                          setIsMenuOpen(false);
                        }}
                        className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                      >
                        home
                      </button>
                      <div className="border-t border-gray-200 my-2" />
                      <button
                        onClick={() => {
                          onLogout?.();
                          setIsMenuOpen(false);
                        }}
                        className="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                      >
                        Logout
                      </button>
                    </>
                  ) : (
                    <>
                      <button
                        onClick={() => {
                          onLogin?.();
                          setIsMenuOpen(false);
                        }}
                        className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                      >
                        Login
                      </button>
                      <button
                        onClick={() => {
                          onEnterprise?.();
                          setIsMenuOpen(false);
                        }}
                        className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"
                      >
                        AppliFlow Enterprise
                      </button>
                    </>
                  )}
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </nav>
  );
}