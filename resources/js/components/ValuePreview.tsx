import { JobQueueAnimation } from "./JobQueueAnimation";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

export function ValuePreview() {
  const { ref, isVisible } = useScrollAnimation();

  return (
    <section className="w-full bg-white py-20" ref={ref}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="grid lg:grid-cols-2 gap-12 items-center">
          {/* Left side - Content */}
          <div className={`fade-in ${isVisible ? 'visible' : ''}`}>
            <h2 className="text-[#1A1A1A] mb-6">
              See matches instantly
            </h2>
            <p className="text-lg text-[#4A4A4A] mb-8 max-w-lg">
              Upload your resume and AppliFlow will show you tech jobs tailored to your skills.
            </p>
          </div>

          {/* Right side - Animated Job Queue */}
          <div className={`relative fade-in fade-in-delay-2 ${isVisible ? 'visible' : ''}`}>
            <JobQueueAnimation />
          </div>
        </div>
      </div>
    </section>
  );
}