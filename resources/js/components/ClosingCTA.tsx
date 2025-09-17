import { Button } from "./ui/button";

interface ClosingCTAProps {
  onSeeMatches?: () => void;
  onDashboard?: () => void;
  isAuthenticated?: boolean;
}

export function ClosingCTA({ onSeeMatches, onDashboard, isAuthenticated }: ClosingCTAProps) {
  return (
    <section className="w-full hero-gradient py-20">
      <div className="max-w-4xl mx-auto px-6 text-center">
        <h2 className="text-white mb-8 max-w-3xl mx-auto">
          Stop job hunting alone. Get your copilot today.
        </h2>
        
        <Button 
          className="bg-white text-[#2D5BFF] hover:bg-[#F7F8FA] px-12 py-4 text-lg font-semibold rounded-lg border-0 transition-all duration-300 shadow-lg btn-glow-white"
          size="lg"
          onClick={isAuthenticated ? onDashboard : onSeeMatches}
        >
          See Your Matches
        </Button>
      </div>
    </section>
  );
}