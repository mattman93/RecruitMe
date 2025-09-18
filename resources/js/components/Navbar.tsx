import { Button } from "./ui/button";
import { ImageWithFallback } from "./figma/ImageWithFallback";
import { Menu } from "lucide-react";

interface NavbarProps {
  isAuthenticated?: boolean;
  onLogout?: () => void;
  onLogin?: () => void;
  onHome?: () => void;
  onDashboard?: () => void;
}

export function Navbar({ isAuthenticated, onLogout, onLogin, onHome, onDashboard }: NavbarProps) {
  return (
    <nav className="w-full bg-white border-b border-[#E6E9ED] px-6 py-4">
      <div className="max-w-7xl mx-auto flex items-center justify-between">
        {/* Logo */}
        <button onClick={onHome} className="flex items-center">
          <div className="w-10 h-10 bg-[#2D5BFF] rounded-lg flex items-center justify-center hover:bg-[#1E3FCC] transition-colors">
            <span className="text-white font-bold text-lg mono">AF</span>
          </div>
        </button>

        {/* Right side navigation */}
        <div className="flex items-center gap-4">
          {!isAuthenticated ? (
            <>
              <button 
                onClick={onLogin}
                className="text-[#4A4A4A] hover:text-[#1A1A1A] transition-colors"
              >
                Login
              </button>
              
              <Button 
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
                Jobs Dashboard
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
          
          <Button
            variant="ghost"
            size="icon"
            className="p-2 hover:bg-[#F5F8FF]"
          >
            <Menu className="h-5 w-5 text-[#4A4A4A]" />
          </Button>
        </div>
      </div>
    </nav>
  );
}