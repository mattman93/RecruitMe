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
      description: "Get your own dedicated domain with fully customized job application pages that match your company branding and culture."
    },
    {
      icon: <Target className="h-6 w-6" />,
      title: "Unlimited Job Listings",
      description: "Post unlimited job openings across all departments and locations with advanced filtering and search capabilities."
    },
    {
      icon: <Building2 className="h-6 w-6" />,
      title: "Enterprise Support",
      description: "Dedicated account management, priority support, and custom onboarding for your organization."
    }
  ];

  const plans = [
    {
      name: "Enterprise",
      price: "$199",
      period: "per month",
      description: "Complete enterprise solution",
      features: [
        "Unlimited job listings",
        "Custom branded job listing page with dedicated domain",
        "Priority support"
      ],
      isPopular: true
    }
  ];

  return (
    <div className="min-h-screen bg-white">
      {/* Hero Section */}
      <section className="bg-gradient-to-br from-[#F7F8FA] to-white" style={{ paddingTop: '80px', paddingBottom: '0px' }}>
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
        <div className="max-w-7xl mx-auto px-2">
          
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
              Simple, Transparent Pricing
            </h2>
            <p className="text-lg text-[#4A4A4A] max-w-2xl mx-auto">
              One plan with everything you need. No hidden fees, no surprises.
            </p>
          </div>

          <div className="flex justify-center">
            <Card
              className="p-8 border-[#E6E9ED] ring-2 ring-[#2D5BFF] shadow-xl transition-all max-w-md w-full relative"
            >
              <Badge className="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-[#2D5BFF] text-white">
                Most Popular
              </Badge>

              <div className="text-center mb-6">
                <h3 className="mb-2 text-[#1A1A1A]">{plans[0].name}</h3>
                <div className="mb-2">
                  <span className="text-3xl font-bold text-[#1A1A1A]">{plans[0].price}</span>
                  <span className="text-[#4A4A4A] ml-1">/{plans[0].period}</span>
                </div>
                <p className="text-[#4A4A4A]">{plans[0].description}</p>
              </div>

              <ul className="space-y-3 mb-8">
                {plans[0].features.map((feature, featureIndex) => (
                  <li key={featureIndex} className="flex items-start gap-3">
                    <Check className="h-5 w-5 text-[#28A745] mt-0.5 flex-shrink-0" />
                    <span className="text-[#4A4A4A]">{feature}</span>
                  </li>
                ))}
              </ul>

              <Button
                onClick={() => {
                  setSelectedPricingTier(plans[0].name);
                  setShowContactForm(true);
                }}
                className="w-full bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white"
              >
                Contact Sales
              </Button>
            </Card>
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