import { Star, Quote } from "lucide-react";

export function TestimonialsSection() {
  return (
    <div>
      <div className="max-w-6xl mx-auto px-6">
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-bold text-white mb-4">
            Success Stories
          </h2>
          <p className="text-xl text-white/90 max-w-3xl mx-auto">
            See what our users are saying about their job search success
          </p>
        </div>

        {/* Stats */}
        <div className="flex flex-col sm:flex-row gap-8 justify-center items-center mb-16 success-stories">
          <div className="text-center">
            <div className="text-4xl md:text-5xl font-bold text-primary mb-2">10,000+</div>
            <div className="text-white/80">Active Users</div>
          </div>
          <div className="text-center">
            <div className="text-4xl md:text-5xl font-bold text-primary mb-2">3x</div>
            <div className="text-white/80">Higher Interview Rate</div>
          </div>
          <div className="text-center">
            <div className="text-4xl md:text-5xl font-bold text-primary mb-2">95%</div>
            <div className="text-white/80">User Satisfaction</div>
          </div>
        </div>

        {/* Testimonials */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div className="bg-white/10 backdrop-blur-sm rounded-xl p-6 border testimonial-card">
            <div className="flex items-center mb-4">
              {[...Array(5)].map((_, i) => (
                <Star key={i} className="w-5 h-5 text-yellow-400 fill-current" />
              ))}
            </div>
            <Quote className="w-8 h-8 text-primary/60 mb-4" />
            <p className="text-white/90 mb-4">
              "I got 5 interviews in my first week! AppliFlow found opportunities I never would have discovered on my own."
            </p>
            <div className="flex items-center">
              <div className="w-10 h-10 bg-primary/20 rounded-full flex items-center justify-center mr-3">
                <span className="text-primary font-semibold">SM</span>
              </div>
              <div>
                <div className="text-white font-semibold">Sarah M.</div>
                <div className="text-white/70 text-sm">Software Engineer</div>
              </div>
            </div>
          </div>

          <div className="bg-white/10 backdrop-blur-sm rounded-xl p-6 border testimonial-card">
            <div className="flex items-center mb-4">
              {[...Array(5)].map((_, i) => (
                <Star key={i} className="w-5 h-5 text-yellow-400 fill-current" />
              ))}
            </div>
            <Quote className="w-8 h-8 text-primary/60 mb-4" />
            <p className="text-white/90 mb-4">
              "The AI matching is incredible. It found my dream job at a company I didn't even know was hiring."
            </p>
            <div className="flex items-center">
              <div className="w-10 h-10 bg-primary/20 rounded-full flex items-center justify-center mr-3">
                <span className="text-primary font-semibold">DL</span>
              </div>
              <div>
                <div className="text-white font-semibold">David L.</div>
                <div className="text-white/70 text-sm">Marketing Manager</div>
              </div>
            </div>
          </div>

          <div className="bg-white/10 backdrop-blur-sm rounded-xl p-6 border testimonial-card">
            <div className="flex items-center mb-4">
              {[...Array(5)].map((_, i) => (
                <Star key={i} className="w-5 h-5 text-yellow-400 fill-current" />
              ))}
            </div>
            <Quote className="w-8 h-8 text-primary/60 mb-4" />
            <p className="text-white/90 mb-4">
              "Saved me hours every week. The automatic applications feature is a game-changer for busy professionals."
            </p>
            <div className="flex items-center">
              <div className="w-10 h-10 bg-primary/20 rounded-full flex items-center justify-center mr-3">
                <span className="text-primary font-semibold">JC</span>
              </div>
              <div>
                <div className="text-white font-semibold">Jessica C.</div>
                <div className="text-white/70 text-sm">Data Analyst</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}