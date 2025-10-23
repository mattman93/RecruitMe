import { useState, useCallback, useEffect } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import { EmbeddedCheckoutProvider, EmbeddedCheckout } from '@stripe/react-stripe-js';
import { Card } from './ui/card';
import { Button } from './ui/button';
import { ArrowLeft, Check, Sparkles, Rocket } from 'lucide-react';

// Stripe promise will be initialized after fetching config
let stripePromise: Promise<any> | null = null;

interface SubscribeProps {
  onBack?: () => void;
  isAuthenticated?: boolean;
}

export function Subscribe({ onBack, isAuthenticated = false }: SubscribeProps) {
  // TEMPORARY: Set to null for normal flow, or set to a price_id to test checkout directly
  const [priceId, setPriceId] = useState<string | null>(null); // Change to price ID to test checkout
  const [pricingConfig, setPricingConfig] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  console.log('Subscribe component - isAuthenticated:', isAuthenticated);

  // Fetch pricing configuration from backend
  useEffect(() => {
    const fetchPricingConfig = async () => {
      try {
        const response = await fetch('/api/stripe/pricing-config', {
          credentials: 'include',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        if (!response.ok) {
          throw new Error(`Failed to load pricing: ${response.status}`);
        }

        const data = await response.json();
        setPricingConfig(data);

        // Initialize Stripe with the publishable key from backend
        if (!stripePromise && data.publishable_key) {
          stripePromise = loadStripe(data.publishable_key);
        }

        // Check for plan query parameter (e.g., ?plan=starter or ?plan=pro)
        const urlParams = new URLSearchParams(window.location.search);
        const plan = urlParams.get('plan');

        if (plan && data.prices) {
          if (plan === 'starter' && data.prices.starter) {
            setPriceId(data.prices.starter);
          } else if (plan === 'pro' && data.prices.pro) {
            setPriceId(data.prices.pro);
          }
        }

        setLoading(false);
      } catch (error) {
        console.error('Failed to fetch pricing config:', error);
        setError(error instanceof Error ? error.message : 'Failed to load pricing');
        setLoading(false);
      }
    };

    fetchPricingConfig();
  }, []);

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

  // Build plans with price IDs from backend config
  const plans = (pricingConfig && pricingConfig.prices) ? [
    {
      name: "Self Starter Tier",
      price: "$29",
      period: "per month",
      description: "Perfect for individual job seekers getting started",
      icon: <Sparkles className="h-8 w-8" />,
      priceId: pricingConfig.prices.starter,
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
      priceId: pricingConfig.prices.pro,
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
  ] : [];

  // Show loading state while fetching pricing config
  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white">
        <div className="flex items-center space-x-2">
          <div className="animate-spin rounded-full h-8 w-8 border-2 border-primary border-t-transparent"></div>
          <span className="text-primary">Loading pricing...</span>
        </div>
      </div>
    );
  }

  // Show error state if pricing config failed to load
  if (error) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-white">
        <div className="text-center max-w-md mx-auto px-6">
          <div className="mb-6">
            <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-red-100 mb-4">
              <span className="text-red-600 text-2xl">⚠️</span>
            </div>
            <h2 className="text-2xl font-bold text-[#1A1A1A] mb-2">Unable to Load Pricing</h2>
            <p className="text-[#4A4A4A] mb-6">{error}</p>
          </div>
          <div className="flex flex-col gap-3">
            <Button onClick={() => window.location.reload()} className="bg-[#2D5BFF] hover:bg-[#1E3FCC] text-white">
              Try Again
            </Button>
            {onBack && (
              <Button onClick={onBack} variant="outline" className="border-[#E6E9ED]">
                Go Back
              </Button>
            )}
          </div>
        </div>
      </div>
    );
  }

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
    <div className="min-h-screen bg-gradient-to-br from-[#F5F8FF] to-white">
      <div className="pt-12 pb-8">
        <div className="max-w-4xl mx-auto px-6">
          <Button
            variant="ghost"
            onClick={() => setPriceId(null)}
            className="mb-6 hover:bg-[#F5F8FF]"
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Plans
          </Button>

          <div className="text-center mb-8">
            {/* Purple accent badge */}
            <div className="inline-flex items-center justify-center mb-4">
              <div className="flex items-center gap-2 px-4 py-2 bg-[#2D5BFF] rounded-full">
                <div className="w-2 h-2 bg-white rounded-full animate-pulse"></div>
                <span className="text-sm font-medium text-white">Secure Checkout</span>
              </div>
            </div>

            <h1 className="text-3xl font-bold text-[#1A1A1A] mb-2">
              Complete Your Subscription
            </h1>
            <p className="text-[#4A4A4A]">
              Join AppliFlow and accelerate your job search
            </p>
          </div>

          {/* Branded container with purple accent border */}
          <div className="relative">
            {/* Purple gradient border effect */}
            <div className="absolute -inset-0.5 bg-gradient-to-r from-[#2D5BFF] to-[#8B5CF6] rounded-lg opacity-20 blur"></div>

            {/* Main checkout container */}
            <div className="relative bg-white rounded-lg shadow-xl p-8 border-2 border-[#2D5BFF] border-opacity-20">
              {stripePromise ? (
                <EmbeddedCheckoutProvider
                  stripe={stripePromise}
                  options={{ fetchClientSecret }}
                >
                  <EmbeddedCheckout />
                </EmbeddedCheckoutProvider>
              ) : (
                <div className="flex items-center justify-center py-12">
                  <div className="text-center">
                    <div className="animate-spin rounded-full h-8 w-8 border-2 border-[#2D5BFF] border-t-transparent mx-auto mb-4"></div>
                    <p className="text-[#4A4A4A]">Initializing payment...</p>
                  </div>
                </div>
              )}
            </div>
          </div>

          {/* Trust badges */}
          <div className="mt-6 flex items-center justify-center gap-6 text-sm text-[#4A4A4A]">
            <div className="flex items-center gap-2">
              <svg className="w-5 h-5 text-[#2D5BFF]" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clipRule="evenodd" />
              </svg>
              <span>Secure Payment</span>
            </div>
            <div className="flex items-center gap-2">
              <svg className="w-5 h-5 text-[#2D5BFF]" fill="currentColor" viewBox="0 0 20 20">
                <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
              </svg>
              <span>Cancel Anytime</span>
            </div>
            <div className="flex items-center gap-2">
              <svg className="w-5 h-5 text-[#2D5BFF]" fill="currentColor" viewBox="0 0 20 20">
                <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8zM12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
              </svg>
              <span>Money-Back Guarantee</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
