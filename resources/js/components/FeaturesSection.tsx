import { Shield, Clock, Target, Users, Bot, Zap } from "lucide-react";

export function FeaturesSection() {
  return (
    <div className="py-20 bg-background">
      <div className="max-w-6xl mx-auto px-6">
        <div className="text-center mb-16">
          <h2 className="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4">
            Why Choose AppliFlow?
          </h2>
          <p className="text-xl text-gray-600 dark:text-gray-300 max-w-3xl mx-auto">
            Our AI-powered platform streamlines your job search, saving you time while maximizing your opportunities
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div className="bg-card rounded-xl p-6 shadow-lg border border-border hover:shadow-xl transition-shadow">
            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
              <Bot className="w-6 h-6 text-primary" />
            </div>
            <h3 className="text-xl font-semibold text-card-foreground mb-3">AI-Powered Matching</h3>
            <p className="text-gray-600 dark:text-gray-300">
              Advanced algorithms analyze your skills and experience to find the perfect job matches automatically
            </p>
          </div>

          <div className="bg-card rounded-xl p-6 shadow-lg border border-border hover:shadow-xl transition-shadow">
            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
              <Clock className="w-6 h-6 text-primary" />
            </div>
            <h3 className="text-xl font-semibold text-card-foreground mb-3">Save Time</h3>
            <p className="text-gray-600 dark:text-gray-300">
              No more manual applications. We apply to relevant positions for you 24/7, even while you sleep
            </p>
          </div>

          <div className="bg-card rounded-xl p-6 shadow-lg border border-border hover:shadow-xl transition-shadow">
            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
              <Target className="w-6 h-6 text-primary" />
            </div>
            <h3 className="text-xl font-semibold text-card-foreground mb-3">Higher Success Rate</h3>
            <p className="text-gray-600 dark:text-gray-300">
              Our targeted approach increases your interview rate by 3x compared to traditional job hunting
            </p>
          </div>

          <div className="bg-card rounded-xl p-6 shadow-lg border border-border hover:shadow-xl transition-shadow">
            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
              <Shield className="w-6 h-6 text-primary" />
            </div>
            <h3 className="text-xl font-semibold text-card-foreground mb-3">Privacy Protected</h3>
            <p className="text-gray-600 dark:text-gray-300">
              Your personal information is encrypted and secure. We never share your data without permission
            </p>
          </div>

          <div className="bg-card rounded-xl p-6 shadow-lg border border-border hover:shadow-xl transition-shadow">
            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
              <Users className="w-6 h-6 text-primary" />
            </div>
            <h3 className="text-xl font-semibold text-card-foreground mb-3">Trusted by Thousands</h3>
            <p className="text-gray-600 dark:text-gray-300">
              Join over 10,000+ professionals who have successfully found their dream jobs through our platform
            </p>
          </div>

          <div className="bg-card rounded-xl p-6 shadow-lg border border-border hover:shadow-xl transition-shadow">
            <div className="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
              <Zap className="w-6 h-6 text-primary" />
            </div>
            <h3 className="text-xl font-semibold text-card-foreground mb-3">Lightning Fast</h3>
            <p className="text-gray-600 dark:text-gray-300">
              Get started in under 2 minutes. Upload your resume and start getting matched immediately
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}