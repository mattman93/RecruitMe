import { useState, useCallback } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { EmbeddedCheckoutProvider, EmbeddedCheckout } from '@stripe/react-stripe-js';
import { Card } from './ui/card';
import { Button } from './ui/button';
import { ArrowLeft, Check, Sparkles, Rocket } from 'lucide-react';

// Load Stripe with publishable key from environment
const stripePromise = loadStripe(import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY);

interface SubscribeProps {
  onBack?: () => void;
  isAuthenticated?: boolean;
}

export function Subscribe({ onBack, isAuthenticated = false }: SubscribeProps) {
  const [priceId, setPriceId] = useState<string | null>(null);

  console.log('Subscribe component - isAuthenticated:', isAuthenticated);

  const fetchClientSecret = useCallback(async () => {
    // Get CSRF token from API
    const tokenResponse = await fetch('/api/csrf-token', {
      credentials: 'include',
    });
    const { token } = await tokenResponse.json();

    // Create a checkout session on the server
    const response = await fetch('/api/stripe/create-checkout-session', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({
        price_id: priceId,
      }),
    });

    const data = await response.json();
    return data.clientSecret;
  }, [priceId]);

  const plans = [
    {
      name: "Self Starter Tier",
      price: "$29",
      period: "per month",
      description: "Perfect for individual job seekers getting started",
      icon: <Sparkles className="h-8 w-8" />,
      priceId: 'price_1SKfpxIIqzkLHLVez9kDO54k',
      features: [
        "Unlimited applications per month",
        "AI-powered resume analysis",
        "Basic application tracking",
        "Email support",
        "Standard job matching algorithm",
      ]
    },
    {
      name: "Pro User Tier",
      price: "$79",
      period: "per month",
      description: "For serious professionals maximizing their job search",
      icon: <Rocket className="h-8 w-8" />,
      priceId: 'price_1SKfqIIIqzkLHLVe6HuiV8wh',
      features: [
        "Unlimited job applications",
        "Advanced AI resume & cover letter generation",
        "Priority application tracking & reminders",
        "Priority email & chat support",
        "Premium job matching with salary insights",
        "Interview preparation tools",
        "Advanced analytics & success metrics",
        "LinkedIn profile authentication",
        "Personalized career coaching tips",
        "Early access to new features"
      ],
      isPopular: true
    }
  ];

  // If no price selected yet, show pricing options
  if (!priceId) {
    return (
      <div className="min-h-screen bg-white">
        {/* Hero Section */}
        <section className="bg-gradient-to-br from-[#F7F8FA] to-white pt-20 pb-16">
          <div className="max-w-7xl mx-auto px-6">
            {onBack && (
              <Button
                variant="ghost"
                onClick={onBack}
                className="mb-6"
              >
                <ArrowLeft className="mr-2 h-4 w-4" />
                {isAuthenticated ? 'Back to Dashboard' : 'Back'}
              </Button>
            )}
            <div className="text-center mb-16">
              <h1 className="mb-6 text-[#1A1A1A] text-4xl font-bold">
                Choose Your AppliFlow Plan
              </h1>
              <p className="text-xl max-w-3xl mx-auto text-[#4A4A4A] mb-8">
                Select the perfect plan to accelerate your job search with AI-powered tools and insights.
              </p>
            </div>
          </div>
        </section>

        {/* Pricing Cards Section */}
        <section className="py-20 bg-[#F7F8FA]">
          <div className="max-w-6xl mx-auto px-6">
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '2rem' }} className="grid grid-cols-1 md:grid-cols-2 gap-8">
              {plans.map((plan, index) => (
                <Card
                  key={index}
                  className={`p-8 border-2 relative ${
                    plan.isPopular ? 'shadow-xl' : 'hover:shadow-lg'
                  } transition-all`}
                  style={plan.isPopular ? { borderColor: '#8B5CF6' } : { borderColor: '#E6E9ED' }}
                >
                  {plan.isPopular && (
                    <div className="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-[#2D5BFF] text-white px-4 py-1 rounded-full text-sm font-semibold">
                      Most Popular
                    </div>
                  )}

                  <div className="text-center mb-6 mt-8">
                    <div className="flex justify-center mb-4">
                      <div className="p-3 bg-[#F5F8FF] rounded-lg text-[#2D5BFF]">
                        {plan.icon}
                      </div>
                    </div>
                    <h3 className="mb-2 text-[#1A1A1A] text-2xl font-bold">{plan.name}</h3>
                    <div className="mb-2">
                      <span className="text-3xl font-bold text-[#1A1A1A]">{plan.price}</span>
                      <span className="text-[#4A4A4A] ml-1">/{plan.period}</span>
                    </div>
                    <p className="text-[#4A4A4A]">{plan.description}</p>
                  </div>

                  <ul className="space-y-3 mb-8 min-h-[300px]">
                    {plan.features.map((feature, featureIndex) => (
                      <li key={featureIndex} className="flex items-start gap-3">
                        <Check className="h-5 w-5 text-[#28A745] mt-0.5 flex-shrink-0" />
                        <span className="text-[#4A4A4A]">{feature}</span>
                      </li>
                    ))}
                  </ul>

                  <Button
                    className="w-full bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white"
                    onClick={() => setPriceId(plan.priceId)}
                  >
                    Launch Your Membership
                  </Button>
                </Card>
              ))}
            </div>
          </div>
        </section>

        {/* FAQ or Additional Info Section */}
        <section className="py-20 bg-white">
          <div className="max-w-4xl mx-auto px-6 text-center">
            <h2 className="mb-6 text-[#1A1A1A] text-3xl font-bold">
              Not Sure Which Plan Is Right for You?
            </h2>
            <p className="text-lg text-[#4A4A4A] mb-8 max-w-2xl mx-auto">
              Start with the Self Starter Tier and upgrade anytime as your job search evolves.
              All plans include a 14-day money-back guarantee.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button variant="outline" className="border-[#E6E9ED] hover:bg-[#F5F8FF] px-8 py-3">
                Compare Plans
              </Button>
              <Button variant="outline" className="border-[#E6E9ED] hover:bg-[#F5F8FF] px-8 py-3">
                Contact Support
              </Button>
            </div>
          </div>
        </section>
      </div>
    );
  }

  // Show embedded checkout
  return (
    <div className="min-h-screen bg-white">
      <div className="bg-gradient-to-br from-[#F7F8FA] to-white pt-12 pb-8">
        <div className="max-w-4xl mx-auto px-6">
          <Button
            variant="ghost"
            onClick={() => setPriceId(null)}
            className="mb-6"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Plans
          </Button>

          <div className="text-center mb-8">
            <h1 className="text-3xl font-bold text-[#1A1A1A] mb-2">
              Complete Your Subscription
            </h1>
            <p className="text-[#4A4A4A]">
              Secure payment powered by Stripe
            </p>
          </div>

          <div className="bg-white rounded-lg shadow-lg p-6">
            <EmbeddedCheckoutProvider
              stripe={stripePromise}
              options={{ fetchClientSecret }}
            >
              <EmbeddedCheckout />
            </EmbeddedCheckoutProvider>
          </div>
        </div>
      </div>
    </div>
  );
}
