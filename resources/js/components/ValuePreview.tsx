import { JobQueueAnimation } from "./JobQueueAnimation";
import { useScrollAnimation } from "./hooks/useScrollAnimation";
import { Zap } from "lucide-react";

export function ValuePreview() {
  const { ref, isVisible } = useScrollAnimation();

  return (
    <section className="w-full bg-white py-20" ref={ref}>
      <div className="max-w-6xl mx-auto px-6">
        {/* Header */}
        <div className="text-center mb-12">
          <div
            className="inline-flex items-center gap-2 rounded-full px-4 py-2 mb-4"
            style={{
              background: 'linear-gradient(to right, #8B5CF6, #3B82F6)'
            }}
          >
            <Zap className="h-4 w-4 text-white" />
            <span className="text-sm font-bold text-white">AppliFlow Starter</span>
          </div>
          <h2 className="text-[#1A1A1A] mb-4">
            Your Job Lead Dashboard
          </h2>
          <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
            Get access to curated job leads where we select the most relevant opportunities and let you apply at the click of a button
          </p>
        </div>

        {/* Animated Job Queue */}
        <div className={`relative fade-in ${isVisible ? 'visible' : ''}`}>
          <JobQueueAnimation />
        </div>
      </div>
    </section>
  );
}