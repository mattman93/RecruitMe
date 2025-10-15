import { Button } from "./ui/button";
import { useParallax } from "./hooks/useScrollAnimation";
import { LavaLampBackground } from "./LavaLampBackground";

interface HeroSectionProps {
  onSeeMatches?: () => void;
  onDashboard?: () => void;
  isAuthenticated?: boolean;
}

export function HeroSection({ onSeeMatches, onDashboard, isAuthenticated }: HeroSectionProps) {
  const offsetY = useParallax();

  return (
    <section className="w-full min-h-screen relative overflow-hidden">
      {/* Animated Lava Lamp Background */}
      <LavaLampBackground />
    {/* <section className="w-full hero-gradient relative overflow-hidden"> */}
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
        
        <p className="text-lg text-white/90 mb-12 max-w-2xl mx-auto">
          Find the best tech jobs, cut the busywork, and apply with confidence.
        </p>
        
        <Button 
          className="bg-white text-[#6366f1] hover:bg-white/90 px-8 py-3 font-semibold rounded-lg border-0 transition-all duration-300 shadow-lg btn-glow-white"
          size="lg"
          onClick={isAuthenticated ? onDashboard : onSeeMatches}
        >
          See Your Matches
        </Button>
      </div>
    </section>
  );
}