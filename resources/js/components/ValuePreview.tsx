import { ImageWithFallback } from "./figma/ImageWithFallback";
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

          {/* Right side - Mock UI Screenshot */}
          <div className={`relative fade-in fade-in-delay-2 ${isVisible ? 'visible' : ''}`}>
            <div className="bg-[#F7F8FA] rounded-2xl p-6 shadow-lg">
              <ImageWithFallback
                src="https://images.unsplash.com/photo-1575388902449-6bca946ad549?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxkYXNoYm9hcmQlMjBpbnRlcmZhY2UlMjBjbGVhbnxlbnwxfHx8fDE3NTgxMjE2NjJ8MA&ixlib=rb-4.1.0&q=80&w=1080&utm_source=figma&utm_medium=referral"
                alt="Job queue interface mockup"
                className="w-full h-auto rounded-xl"
              />
              
              {/* Overlay elements to make it look more like a job interface */}
              <div className="absolute inset-6 bg-gradient-to-t from-white/80 via-transparent to-transparent rounded-xl">
                <div className="absolute bottom-4 left-4 right-4">
                  <div className="bg-white rounded-lg p-3 shadow-sm border border-[#E6E9ED] mb-2">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-[#2D5BFF] rounded-lg flex items-center justify-center">
                        <span className="text-white text-xs font-bold">G</span>
                      </div>
                      <div>
                        <div className="h-2 bg-[#E6E9ED] rounded w-24 mb-1"></div>
                        <div className="h-1.5 bg-[#E6E9ED] rounded w-16"></div>
                      </div>
                      <div className="ml-auto">
                        <div className="bg-[#28A745] text-white text-xs px-2 py-1 rounded-full">98% match</div>
                      </div>
                    </div>
                  </div>
                  
                  <div className="bg-white rounded-lg p-3 shadow-sm border border-[#E6E9ED]">
                    <div className="flex items-center gap-3">
                      <div className="w-8 h-8 bg-[#FFB800] rounded-lg flex items-center justify-center">
                        <span className="text-white text-xs font-bold">S</span>
                      </div>
                      <div>
                        <div className="h-2 bg-[#E6E9ED] rounded w-20 mb-1"></div>
                        <div className="h-1.5 bg-[#E6E9ED] rounded w-14"></div>
                      </div>
                      <div className="ml-auto">
                        <div className="bg-[#28A745] text-white text-xs px-2 py-1 rounded-full">94% match</div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}