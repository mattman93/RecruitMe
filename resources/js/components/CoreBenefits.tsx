import { Target, Zap, User } from "lucide-react";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

export function CoreBenefits() {
  const { ref, isVisible } = useScrollAnimation();
  
  const benefits = [
    {
      icon: Target,
      title: "Smart Matching",
      description: "No spam. Only jobs that fit your skills and career path."
    },
    {
      icon: Zap,
      title: "Semi-Automated Applications",
      description: "Approve with one click — we handle the tedious form filling."
    },
    {
      icon: User,
      title: "Built for Tech Pros",
      description: "Tailored to software engineers, product managers, and designers."
    }
  ];

  return (
    <section className="w-full bg-[#F7F8FA] py-20" ref={ref}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="grid md:grid-cols-3 gap-8">
          {benefits.map((benefit, index) => {
            const IconComponent = benefit.icon;
            return (
              <div 
                key={index} 
                className={`text-center fade-in fade-in-delay-${index + 1} ${isVisible ? 'visible' : ''}`}
              >
                <div className="w-16 h-16 bg-[#2D5BFF] rounded-2xl flex items-center justify-center mx-auto mb-6">
                  <IconComponent className="w-8 h-8 text-white" />
                </div>
                <h3 className="text-[#1A1A1A] mb-4">
                  {benefit.title}
                </h3>
                <p className="text-[#4A4A4A] max-w-sm mx-auto">
                  {benefit.description}
                </p>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}