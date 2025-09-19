import { Upload, Eye, Send } from "lucide-react";
import { ImageWithFallback } from "./figma/ImageWithFallback";
import { ResumeUploadAnimation } from "./ResumeUploadAnimation";
import { JobReviewAnimation } from "./JobReviewAnimation";
import { JobApplicationAnimation } from "./JobApplicationAnimation";
import { useScrollAnimation } from "./hooks/useScrollAnimation";

export function HowItWorks() {
  const { ref, isVisible } = useScrollAnimation();
  
  const steps = [
    {
      icon: Upload,
      title: "Upload your resume",
      description: "Quick and secure upload process",
      image: "https://images.unsplash.com/photo-1554224155-cfa08c2a758f?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxyZXN1bWUlMjBkb2N1bWVudCUyMHVwbG9hZHxlbnwxfHx8fDE3NTgxMjE2NjJ8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral",
      isAnimated: true,
      animationType: "upload"
    },
    {
      icon: Eye,
      title: "Review matches",
      description: "See personalized job recommendations",
      image: "https://images.unsplash.com/photo-1575388902449-6bca946ad549?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkYXNoYm9hcmQlMjBpbnRlcmZhY2UlMjBjbGVhbnxlbnwxfHx8fDE3NTgxMjE2NjJ8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral",
      isAnimated: true,
      animationType: "review"
    },
    {
      icon: Send,
      title: "Apply with confidence",
      description: "One-click applications that work",
      image: "https://images.unsplash.com/photo-1575388902449-6bca946ad549?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkYXNoYm9hcmQlMjBpbnRlcmZhY2UlMjBjbGVhbnxlbnwxfHx8fDE3NTgxMjE2NjJ8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral",
      isAnimated: true,
      animationType: "apply"
    }
  ];

  return (
    <section className="w-full bg-white py-20" ref={ref}>
      <div className="max-w-6xl mx-auto px-6">
        <div className="text-center mb-16">
          <h2 className="text-[#1A1A1A] mb-4">
            How It Works
          </h2>
        </div>

        <div className="space-y-16">
          {steps.map((step, index) => {
            const IconComponent = step.icon;
            const isEven = index % 2 === 0;
            
            return (
              <div key={index} className={`grid lg:grid-cols-2 gap-12 items-center ${isEven ? '' : 'lg:grid-flow-col-dense'} fade-in fade-in-delay-${index + 1} ${isVisible ? 'visible' : ''}`}>
                {/* Content */}
                <div className={`${isEven ? '' : 'lg:col-start-2'}`}>
                  <div className="flex items-center gap-4 mb-6">
                    <div className="w-12 h-12 bg-[#2D5BFF] rounded-xl flex items-center justify-center">
                      <IconComponent className="w-6 h-6 text-white" />
                    </div>
                    <span className="text-sm font-semibold text-[#2D5BFF] mono">
                      STEP {index + 1}
                    </span>
                  </div>
                  
                  <div>
                    <h3 className="text-[#1A1A1A] mb-4 max-w-md">
                      {step.title}
                    </h3>
                    <p className="text-[#7A7A7A] text-sm max-w-md">
                      {step.description}
                    </p>
                  </div>
                </div>

                {/* Visual */}
                <div className={`${isEven ? '' : 'lg:col-start-1 lg:row-start-1'}`}>
                  <div className="bg-[#F7F8FA] rounded-2xl p-6 flex items-center justify-center min-h-[300px]">
                    {step.isAnimated ? (
                      step.animationType === "upload" ? (
                        <ResumeUploadAnimation />
                      ) : step.animationType === "review" ? (
                        <JobReviewAnimation />
                      ) : step.animationType === "apply" ? (
                        <JobApplicationAnimation />
                      ) : null
                    ) : (
                      <ImageWithFallback
                        src={step.image}
                        alt={`Step ${index + 1} illustration`}
                        className="w-full h-48 object-cover rounded-xl"
                      />
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}