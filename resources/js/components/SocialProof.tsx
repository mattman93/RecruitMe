import { Check, X } from "lucide-react";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

export function SocialProof() {
  const { ref, isVisible } = useScrollAnimation();
  
  const comparisons = [
    {
      feature: "Quality over quantity",
      jobCopilot: false,
      lazyApply: false,
      appliFlow: true
    },
    {
      feature: "Tech-focused matching",
      jobCopilot: false,
      lazyApply: false,
      appliFlow: true
    },
    {
      feature: "ATS-friendly applications",
      jobCopilot: true,
      lazyApply: false,
      appliFlow: true
    },
    {
      feature: "Human oversight",
      jobCopilot: false,
      lazyApply: false,
      appliFlow: true
    },
    {
      feature: "Spam applications",
      jobCopilot: true,
      lazyApply: true,
      appliFlow: false
    }
  ];

  return (
    <section className="w-full bg-[#F7F8FA] py-20" ref={ref}>
      <div className="max-w-4xl mx-auto px-6">
        <div className={`text-center mb-16 fade-in ${isVisible ? 'visible' : ''}`}>
          <h2 className="text-[#1A1A1A] mb-6">
            Not another "spray and pray" job tool.
          </h2>
          <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
            Unlike generic auto-appliers, AppliFlow focuses on relevance and quality — helping tech workers land interviews faster.
          </p>
        </div>

        <div className={`bg-white rounded-2xl shadow-lg overflow-hidden fade-in fade-in-delay-2 ${isVisible ? 'visible' : ''}`}>
          <div className="grid grid-cols-4 gap-4 p-6 bg-[#F7F8FA] border-b border-[#E6E9ED]">
            <div className="font-semibold text-[#1A1A1A]">Feature</div>
            <div className="text-center font-semibold text-[#7A7A7A]">JobCopilot</div>
            <div className="text-center font-semibold text-[#7A7A7A]">LazyApply</div>
            <div className="text-center font-semibold text-[#2D5BFF]">AppliFlow</div>
          </div>

          {comparisons.map((comparison, index) => (
            <div key={index} className="grid grid-cols-4 gap-4 p-6 border-b border-[#E6E9ED] last:border-b-0">
              <div className="text-[#1A1A1A]">{comparison.feature}</div>
              <div className="text-center">
                {comparison.jobCopilot ? (
                  <Check className="w-5 h-5 text-[#28A745] mx-auto" />
                ) : (
                  <X className="w-5 h-5 text-[#DC3545] mx-auto" />
                )}
              </div>
              <div className="text-center">
                {comparison.lazyApply ? (
                  <Check className="w-5 h-5 text-[#28A745] mx-auto" />
                ) : (
                  <X className="w-5 h-5 text-[#DC3545] mx-auto" />
                )}
              </div>
              <div className="text-center">
                {comparison.appliFlow ? (
                  <Check className="w-5 h-5 text-[#28A745] mx-auto" />
                ) : (
                  <X className="w-5 h-5 text-[#DC3545] mx-auto" />
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}