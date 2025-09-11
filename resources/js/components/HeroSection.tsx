import { Upload, Search, Send, CheckCircle } from "lucide-react";

export function HeroSection() {
  return (
    <div>
      {/* Hero Section with Background Image */}
      <div className="px-6 py-24 hero-background">
        <div className="max-w-6xl mx-auto relative z-10">
          {/* Main Hero Content */}
          <div className="text-center mb-8">
            <div className="mb-6 welcome-text">
              <img 
                src="/images/welcome-appliflow.svg" 
                alt="Welcome to AppliFlow" 
                className="mx-auto max-w-full h-auto"
                style={{ maxHeight: '200px' }}
              />
            </div>
            <p className="text-xl text-white/90 max-w-3xl mx-auto mb-8 leading-relaxed tag-line">
              Get started by uploading your resume and letting our system
              analyze the best job matches for you. Then hit "Apply" and let AppliFlow do the rest.
              <p className="text-lg text-white/80 font-medium">It's that simple</p>
            </p>
          </div>
        </div>
      </div>

      {/* How It Works Section - Navy Gradient Background */}
      <div className="px-6 py-16">
        <div className="max-w-4xl mx-auto">
          <h2 className="text-2xl md:text-3xl text-white text-center font-semibold how-it-works-spacing mb-12">
            How It Works
          </h2>
          <div className="flex flex-col sm:flex-row gap-8 justify-center items-center">
            <div className="text-center">
              <div className="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Upload className="w-8 h-8 text-primary" />
              </div>
              <h3 className="text-white font-semibold mb-2">Upload Resume</h3>
              <p className="text-white/70 text-sm">
                Upload your resume in PDF, DOC, or image format
              </p>
            </div>

            <div className="text-center">
              <div className="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Search className="w-8 h-8 text-primary" />
              </div>
              <h3 className="text-white font-semibold mb-2">AI Analysis</h3>
              <p className="text-white/70 text-sm">
                Our AI analyzes your skills and finds matching opportunities
              </p>
            </div>

            <div className="text-center">
              <div className="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <Send className="w-8 h-8 text-primary" />
              </div>
              <h3 className="text-white font-semibold mb-2">Auto Apply</h3>
              <p className="text-white/70 text-sm">
                We automatically apply to relevant positions for you
              </p>
            </div>

            <div className="text-center">
              <div className="w-16 h-16 bg-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <CheckCircle className="w-8 h-8 text-primary" />
              </div>
              <h3 className="text-white font-semibold mb-2">Get Hired</h3>
              <p className="text-white/70 text-sm">
                Track applications and receive interview invitations
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}