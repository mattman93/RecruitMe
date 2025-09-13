import { Avatar, AvatarFallback, AvatarImage } from "./ui/avatar";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "./ui/dropdown-menu";
import { User, Settings, HelpCircle, LogOut } from "lucide-react";

interface NavbarProps {
  isAuthenticated?: boolean;
  onLogout?: () => void;
}

export function Navbar({ isAuthenticated = false, onLogout }: NavbarProps) {
  return (
    <nav className="w-full bg-card border-b border-border px-6 py-4 shadow-sm">
      <div className="flex justify-between items-center">
        {/* Logo */}
        <div className="flex items-center">
          <img 
            src="/images/appliflow-logo-v2.svg" 
            alt="AppliFlow" 
            className=""
          />
        </div>
        
        {/* User Menu */}
        <div>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button className="rounded-full outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all hover:scale-105">
              <Avatar className="h-10 w-10 cursor-pointer border-2 border-primary/20">
                <AvatarImage src="" alt="Profile" />
                <AvatarFallback className="bg-primary/10 text-primary">
                  <User className="h-5 w-5" />
                </AvatarFallback>
              </Avatar>
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" className="w-48 shadow-lg border border-border">
            <DropdownMenuItem className="hover:bg-primary/5 transition-colors">
              <User className="mr-2 h-4 w-4 text-primary" />
              My Account
            </DropdownMenuItem>
            <DropdownMenuItem className="hover:bg-primary/5 transition-colors">
              <Settings className="mr-2 h-4 w-4 text-primary" />
              Settings
            </DropdownMenuItem>
            <DropdownMenuItem className="hover:bg-primary/5 transition-colors">
              <HelpCircle className="mr-2 h-4 w-4 text-primary" />
              Help
            </DropdownMenuItem>
            {isAuthenticated && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem 
                  className="hover:bg-destructive/5 transition-colors text-destructive"
                  onClick={onLogout}
                >
                  <LogOut className="mr-2 h-4 w-4" onClick={onLogout}/>
                  Sign Out
                </DropdownMenuItem>
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
        </div>
      </div>
    </nav>
  );
}