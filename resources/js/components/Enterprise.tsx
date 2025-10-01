import { useState } from "react";
import { Button } from "./ui/button";
import { Card } from "./ui/card";
import { Badge } from "./ui/badge";
import { Check, Building2, Users, Target, Globe, Shield, Zap, ArrowRight, Star } from "lucide-react";
import { EnterpriseContact } from "./EnterpriseContact";

export function Enterprise() {
  const [showContactForm, setShowContactForm] = useState(false);
  const [selectedPricingTier, setSelectedPricingTier] = useState<string>("");
  const features = [
    {
      icon: <Globe className="h-6 w-6" />,
      title: "Custom Branded Application Portal",
      description: "Get your own subdomain with fully customized job application pages that match your company branding and culture."
    },
    {
      icon: <Target className="h-6 w-6" />,
      title: "Unlimited Job Listings",
      description: "Post unlimited job openings across all departments and locations with advanced filtering and search capabilities."
    },
    {
      icon: <Users className="h-6 w-6" />,
      title: "AI-Powered Candidate Screening",
      description: "Automatically screen and rank candidates based on job requirements, saving your HR team countless hours."
    },
    {
      icon: <Shield className="h-6 w-6" />,
      title: "Advanced Analytics & Reporting",
      description: "Get detailed insights into your hiring funnel, candidate quality, and time-to-hire metrics."
    },
    {
      icon: <Zap className="h-6 w-6" />,
      title: "API Integration",
      description: "Seamlessly integrate with your existing HR systems and ATS platforms for streamlined workflows."
    },
    {
      icon: <Building2 className="h-6 w-6" />,
      title: "Enterprise Support",
      description: "Dedicated account management, priority support, and custom onboarding for your organization."
    }
  ];

  const plans = [
    {
      name: "Starter",
      price: "$299",
      period: "per month",
      description: "Perfect for growing companies",
      features: [
        "Up to 10 active job listings",
        "Basic candidate screening",
        "Standard application portal",
        "Email support",
        "Basic analytics"
      ],
      isPopular: false
    },
    {
      name: "Professional",
      price: "$599",
      period: "per month",
      description: "For established organizations",
      features: [
        "Unlimited job listings",
        "Advanced AI screening",
        "Custom branded portal",
        "Priority support",
        "Advanced analytics",
        "API access",
        "Team collaboration tools"
      ],
      isPopular: true
    },
    {
      name: "Enterprise",
      price: "Custom",
      period: "pricing",
      description: "For large-scale operations",
      features: [
        "Everything in Professional",
        "Dedicated account manager",
        "Custom integrations",
        "SLA guarantees",
        "Advanced security features",
        "Custom reporting",
        "White-label options"
      ],
      isPopular: false
    }
  ];

  const testimonials = [
    {
      name: "Sarah Chen",
      role: "VP of People, TechCorp",
      company: "TechCorp",
      quote: "AppliFlow Enterprise has transformed our hiring process. We've reduced time-to-hire by 40% while improving candidate quality.",
      rating: 5
    },
    {
      name: "Michael Rodriguez",
      role: "Head of Talent, InnovateX",
      company: "InnovateX",
      quote: "The AI screening capabilities are incredible. We can now focus on interviewing only the most qualified candidates.",
      rating: 5
    }
  ];

  return (
    <div className="min-h-screen bg-white">
      {/* Hero Section */}
      <section className="bg-gradient-to-br from-[#F7F8FA] to-white" style={{ paddingTop: '80px', paddingBottom: '64px' }}>
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <Badge className="mb-6 bg-[#F5F8FF] text-[#2D5BFF] border-[#2D5BFF]/20">
              Enterprise Solutions
            </Badge>
            <h1 className="mb-6 text-[#1A1A1A]">
              Scale Your Hiring with AI-Powered Recruitment
            </h1>
            <p className="text-xl max-w-3xl mx-auto text-[#4A4A4A] mb-8">
              Transform your talent acquisition with AppliFlow Enterprise. Get custom application portals, 
              unlimited job listings, and AI-powered candidate screening that saves time and improves hiring quality.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button
                onClick={() => {
                  setSelectedPricingTier("Demo Request");
                  setShowContactForm(true);
                }}
                className="bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white px-8 py-3"
              >
                Schedule Demo
                <ArrowRight className="ml-2 h-4 w-4" />
              </Button>
              <Button variant="outline" className="border-[#E6E9ED] hover:bg-[#F5F8FF] px-8 py-3">
                View Pricing
              </Button>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="py-20">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="mb-4 text-[#1A1A1A]">
              Everything You Need to Hire Better, Faster
            </h2>
            <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
              Our enterprise platform combines cutting-edge AI with intuitive design 
              to streamline every aspect of your recruitment process.
            </p>
          </div>
          
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            {features.map((feature, index) => (
              <Card key={index} className="p-6 border-[#E6E9ED] hover:shadow-lg transition-shadow">
                <div className="flex items-start gap-4">
                  <div className="p-2 bg-[#F5F8FF] rounded-lg text-[#2D5BFF]">
                    {feature.icon}
                  </div>
                  <div>
                    <h3 className="mb-2 text-[#1A1A1A]">{feature.title}</h3>
                    <p className="text-[#4A4A4A]">{feature.description}</p>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Pricing Section */}
      <section className="py-20 bg-[#F7F8FA]">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="mb-4 text-[#1A1A1A]">
              Choose Your Enterprise Plan
            </h2>
            <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
              Flexible pricing options designed to scale with your organization's needs.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            {plans.map((plan, index) => (
              <Card 
                key={index} 
                className={`p-8 border-[#E6E9ED] relative ${
                  plan.isPopular ? 'ring-2 ring-[#2D5BFF] shadow-xl' : 'hover:shadow-lg'
                } transition-all`}
              >
                {plan.isPopular && (
                  <Badge className="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-[#2D5BFF] text-white">
                    Most Popular
                  </Badge>
                )}
                
                <div className="text-center mb-6">
                  <h3 className="mb-2 text-[#1A1A1A]">{plan.name}</h3>
                  <div className="mb-2">
                    <span className="text-3xl font-bold text-[#1A1A1A]">{plan.price}</span>
                    <span className="text-[#4A4A4A] ml-1">/{plan.period}</span>
                  </div>
                  <p className="text-[#4A4A4A]">{plan.description}</p>
                </div>

                <ul className="space-y-3 mb-8">
                  {plan.features.map((feature, featureIndex) => (
                    <li key={featureIndex} className="flex items-start gap-3">
                      <Check className="h-5 w-5 text-[#28A745] mt-0.5 flex-shrink-0" />
                      <span className="text-[#4A4A4A]">{feature}</span>
                    </li>
                  ))}
                </ul>

                <Button
                  onClick={() => {
                    setSelectedPricingTier(plan.name);
                    setShowContactForm(true);
                  }}
                  className={`w-full ${
                    plan.isPopular
                      ? 'bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white'
                      : 'bg-white hover:bg-[#F5F8FF] text-[#2D5BFF] border border-[#2D5BFF]'
                  }`}
                >
                  {plan.name === 'Enterprise' ? 'Contact Sales' : 'Get Started'}
                </Button>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* Testimonials Section */}
      <section className="py-20">
        <div className="max-w-7xl mx-auto px-6">
          <div className="text-center mb-16">
            <h2 className="mb-4 text-[#1A1A1A]">
              Trusted by Leading Companies
            </h2>
            <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
              See how organizations are transforming their hiring with AppliFlow Enterprise.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-8">
            {testimonials.map((testimonial, index) => (
              <Card key={index} className="p-8 border-[#E6E9ED]">
                <div className="flex mb-4">
                  {[...Array(testimonial.rating)].map((_, i) => (
                    <Star key={i} className="h-5 w-5 text-[#FFB800] fill-current" />
                  ))}
                </div>
                <blockquote className="text-lg text-[#1A1A1A] mb-6 italic">
                  "{testimonial.quote}"
                </blockquote>
                <div className="flex items-center gap-4">
                  <div className="w-12 h-12 bg-[#F5F8FF] rounded-full flex items-center justify-center">
                    <span className="text-[#2D5BFF] font-semibold">
                      {testimonial.name.split(' ').map(n => n[0]).join('')}
                    </span>
                  </div>
                  <div>
                    <div className="text-[#1A1A1A] font-medium">{testimonial.name}</div>
                    <div className="text-[#4A4A4A]">{testimonial.role}</div>
                  </div>
                </div>
              </Card>
            ))}
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 bg-gradient-to-r from-[#2D5BFF] to-[#4F46E5]" style={{ background: 'linear-gradient(to right, #2D5BFF, #4F46E5)', paddingTop: '80px', paddingBottom: '80px' }}>
        <div className="max-w-4xl mx-auto px-6 text-center">
          <h2 className="mb-6 text-white">
            Ready to Transform Your Hiring Process?
          </h2>
          <p className="text-xl text-white/90 mb-8 max-w-2xl mx-auto">
            Join hundreds of companies already using AppliFlow Enterprise to hire better, faster, and smarter.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <Button
              onClick={() => {
                setSelectedPricingTier("Demo Request");
                setShowContactForm(true);
              }}
              className="bg-white hover:bg-gray-100 text-[#2D5BFF] px-8 py-3"
            >
              Schedule a Demo
              <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
            <Button
              onClick={() => {
                setSelectedPricingTier("Enterprise");
                setShowContactForm(true);
              }}
              variant="outline"
              className="border-white/30 hover:bg-white/10 text-white px-8 py-3"
              style={{ color: '#2D5BFF', borderColor: '#2D5BFF', backgroundColor: 'white' }}
            >
              Contact Sales
            </Button>
          </div>
        </div>
      </section>

      {/* Enterprise Contact Modal */}
      {showContactForm && (
        <EnterpriseContact
          pricingTier={selectedPricingTier}
          onClose={() => setShowContactForm(false)}
        />
      )}
    </div>
  );
}